#!/usr/bin/env python3
"""
Telegram bridge for Bitaxe OC.

Design:
- allowlist chat IDs only
- controlled operations only (no arbitrary shell from user/LLM)
- natural-language mode via LLM with strict action parsing
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import tempfile
import time
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Any


TOKEN = os.environ.get("TG_BOT_TOKEN", "").strip()
if not TOKEN:
    print("TG_BOT_TOKEN is required", file=sys.stderr)
    sys.exit(2)

ALLOWED_CHAT_IDS_RAW = os.environ.get("TG_ALLOWED_CHAT_IDS", "").strip()
if not ALLOWED_CHAT_IDS_RAW:
    print("TG_ALLOWED_CHAT_IDS is required", file=sys.stderr)
    sys.exit(2)

ALLOWED_CHAT_IDS = {
    int(part.strip())
    for part in ALLOWED_CHAT_IDS_RAW.split(",")
    if part.strip().lstrip("-").isdigit()
}
if not ALLOWED_CHAT_IDS:
    print("No valid TG_ALLOWED_CHAT_IDS parsed", file=sys.stderr)
    sys.exit(2)

STATE_PATH = Path(os.environ.get("TG_STATE_PATH", "/opt/oc/tmp/tg_bridge_state.json"))
EVENT_LOG_PATH = Path(os.environ.get("TG_EVENT_LOG_PATH", "/opt/oc/tmp/tg_bridge_events.log"))
POLL_TIMEOUT_SEC = max(5, min(50, int(os.environ.get("TG_POLL_TIMEOUT_SEC", "25"))))
MAX_TG_MESSAGE = 3500
API_BASE = f"https://api.telegram.org/bot{TOKEN}"

# LLM config
LLM_ENABLED = os.environ.get("TG_LLM_ENABLED", "1").strip() not in {"0", "false", "False"}
LLM_PROVIDER = os.environ.get("TG_LLM_PROVIDER", "pollinations").strip().lower()
LLM_TIMEOUT_SEC = max(8, min(120, int(os.environ.get("TG_LLM_TIMEOUT_SEC", "45"))))
LLM_MAX_HISTORY = max(2, min(24, int(os.environ.get("TG_LLM_MAX_HISTORY", "8"))))

OPENAI_API_KEY = os.environ.get("OPENAI_API_KEY", "").strip()
OPENAI_MODEL = os.environ.get("TG_OPENAI_MODEL", "gpt-4o-mini").strip()
OPENAI_URL = os.environ.get("TG_OPENAI_URL", "https://api.openai.com/v1/chat/completions").strip()

POLLINATIONS_MODEL = os.environ.get("TG_POLLINATIONS_MODEL", "openai-fast").strip()
POLLINATIONS_URL = os.environ.get("TG_POLLINATIONS_URL", "https://text.pollinations.ai/openai").strip()

TR_LOCALE_MAP = str.maketrans(
    {
        "ı": "i",
        "İ": "i",
        "ş": "s",
        "ğ": "g",
        "ü": "u",
        "ö": "o",
        "ç": "c",
    }
)

ACTION_SET = {"none", "status", "quick_test", "live_audit", "master", "log"}
ACTION_PATTERN = re.compile(r"^\s*ACTION\s*:\s*<?\s*(none|status|quick_test|live_audit|master|log)\s*>?\s*$", re.I)


@dataclass(frozen=True)
class ProjectDef:
    code: str
    cwd: str


PROJECTS: dict[str, ProjectDef] = {
    "oc": ProjectDef(code="oc", cwd="/opt/oc"),
}


def normalize_text(raw: str) -> str:
    text = (raw or "").strip().casefold().translate(TR_LOCALE_MAP)
    text = "".join(ch for ch in unicodedata.normalize("NFKD", text) if not unicodedata.combining(ch))
    text = re.sub(r"[^a-z0-9%/\s]", " ", text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def log_event(kind: str, payload: dict[str, Any]) -> None:
    EVENT_LOG_PATH.parent.mkdir(parents=True, exist_ok=True)
    line = json.dumps(
        {
            "ts": int(time.time()),
            "kind": kind,
            "payload": payload,
        },
        ensure_ascii=False,
    )
    with open(EVENT_LOG_PATH, "a", encoding="utf-8") as fp:
        fp.write(line + "\n")
    try:
        os.chmod(EVENT_LOG_PATH, 0o600)
    except OSError:
        pass


def post_form_json(url: str, params: dict[str, Any], timeout: int) -> dict[str, Any]:
    payload = urllib.parse.urlencode(params, doseq=True).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=payload,
        headers={
            "Content-Type": "application/x-www-form-urlencoded",
            "User-Agent": "bitaxe-telegram-bridge/1.0",
            "Accept": "application/json",
        },
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        body = resp.read().decode("utf-8", errors="replace")
    return json.loads(body)


def post_json(url: str, payload: dict[str, Any], headers: dict[str, str] | None, timeout: int) -> dict[str, Any]:
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "User-Agent": "bitaxe-telegram-bridge/1.0",
            "Accept": "application/json",
            **(headers or {}),
        },
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        body = resp.read().decode("utf-8", errors="replace")
    return json.loads(body)


def api_call(method: str, params: dict[str, Any] | None = None, timeout: int = 35) -> dict[str, Any]:
    data = post_form_json(f"{API_BASE}/{method}", params or {}, timeout=timeout)
    if not data.get("ok"):
        raise RuntimeError(f"Telegram API error on {method}: {data}")
    return data


def chunk_text(text: str, max_len: int = MAX_TG_MESSAGE) -> list[str]:
    text = (text or "").strip()
    if not text:
        return ["(boş)"]
    if len(text) <= max_len:
        return [text]

    chunks: list[str] = []
    current: list[str] = []
    current_len = 0
    for line in text.splitlines():
        line_len = len(line) + 1
        if current and current_len + line_len > max_len:
            chunks.append("\n".join(current))
            current = [line]
            current_len = line_len
        elif line_len > max_len:
            if current:
                chunks.append("\n".join(current))
                current = []
                current_len = 0
            for i in range(0, len(line), max_len):
                chunks.append(line[i : i + max_len])
        else:
            current.append(line)
            current_len += line_len
    if current:
        chunks.append("\n".join(current))
    return chunks


def send_message(chat_id: int, text: str) -> None:
    for idx, chunk in enumerate(chunk_text(text), start=1):
        prefix = "" if idx == 1 else f"[{idx}] "
        api_call(
            "sendMessage",
            {
                "chat_id": str(chat_id),
                "text": prefix + chunk,
                "disable_web_page_preview": "true",
            },
        )
    log_event("bot_reply", {"chat_id": chat_id, "preview": (text or "")[:240]})


def run_cmd(command: str, cwd: str, timeout_sec: int = 180) -> tuple[int, str]:
    proc = subprocess.run(
        ["/bin/bash", "-lc", command],
        cwd=cwd,
        capture_output=True,
        text=True,
        timeout=timeout_sec,
        check=False,
    )
    combined = (proc.stdout or "") + ("\n" if proc.stdout and proc.stderr else "") + (proc.stderr or "")
    combined = combined.strip() or "(no output)"
    return proc.returncode, combined


def run_wrapper(project: ProjectDef, wrapper_rel: str, timeout_sec: int) -> tuple[int, str]:
    wrapper_path = Path(project.cwd) / wrapper_rel
    if not wrapper_path.exists():
        return 2, f"Wrapper bulunamadı: {wrapper_path}"
    if not os.access(wrapper_path, os.X_OK):
        return 2, f"Wrapper executable değil: {wrapper_path}"
    return run_cmd(str(wrapper_path), cwd=project.cwd, timeout_sec=timeout_sec)


def load_state() -> dict[str, Any]:
    default = {"offset": 0, "chat_project": {}, "llm_history": {}}
    if not STATE_PATH.exists():
        return default
    try:
        raw = STATE_PATH.read_text(encoding="utf-8")
        data = json.loads(raw)
        if not isinstance(data, dict):
            return default
        data.setdefault("offset", 0)
        data.setdefault("chat_project", {})
        data.setdefault("llm_history", {})
        return data
    except Exception:
        return default


def save_state(state: dict[str, Any]) -> None:
    STATE_PATH.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile("w", encoding="utf-8", dir=str(STATE_PATH.parent), delete=False) as tmp:
        json.dump(state, tmp, ensure_ascii=True)
        tmp.flush()
        os.fsync(tmp.fileno())
        tmp_path = Path(tmp.name)
    tmp_path.replace(STATE_PATH)
    os.chmod(STATE_PATH, 0o600)


def help_text() -> str:
    return (
        "Bitaxe Telegram Asistanı aktif.\n\n"
        "Serbest normal cümle ile yazabilirsin.\n"
        "Örnekler:\n"
        "- Sistem stabil mi?\n"
        "- Canlı denetim yap\n"
        "- Hızlı test çalıştır\n"
        "- Son logları göster\n"
        "- Master yedek al\n\n"
        "Not: Bu bot yalnızca allowlist chat id ile çalışır."
    )


def get_chat_project(state: dict[str, Any], chat_id: int) -> ProjectDef:
    mapping = state.setdefault("chat_project", {})
    code = str(mapping.get(str(chat_id), "oc")).strip().lower()
    if code not in PROJECTS:
        code = "oc"
    return PROJECTS[code]


def set_chat_project(state: dict[str, Any], chat_id: int, code: str) -> bool:
    code = code.strip().lower()
    if code not in PROJECTS:
        return False
    state.setdefault("chat_project", {})[str(chat_id)] = code
    return True


def history_get(state: dict[str, Any], chat_id: int) -> list[dict[str, str]]:
    history_map = state.setdefault("llm_history", {})
    raw = history_map.get(str(chat_id), [])
    if not isinstance(raw, list):
        return []
    cleaned: list[dict[str, str]] = []
    for item in raw[-(LLM_MAX_HISTORY * 2) :]:
        if not isinstance(item, dict):
            continue
        role = str(item.get("role", "")).strip()
        content = str(item.get("content", "")).strip()
        if role not in {"user", "assistant"} or not content:
            continue
        cleaned.append({"role": role, "content": content})
    return cleaned


def history_push(state: dict[str, Any], chat_id: int, role: str, content: str) -> None:
    if role not in {"user", "assistant"}:
        return
    text = (content or "").strip()
    if not text:
        return
    history_map = state.setdefault("llm_history", {})
    key = str(chat_id)
    arr = history_map.get(key)
    if not isinstance(arr, list):
        arr = []
    arr.append({"role": role, "content": text[:1200]})
    history_map[key] = arr[-(LLM_MAX_HISTORY * 2) :]


def cmd_status(project: ProjectDef) -> str:
    if project.code != "oc":
        return f"[durum:{project.code}] unsupported project"
    code, out = run_wrapper(project, "scripts/tg-status.sh", timeout_sec=45)
    if code == 0:
        lines = out.splitlines()
        version = next((ln.split("=", 1)[1] for ln in lines if ln.startswith("version=")), "-")
        sharing = next((ln.split("=", 1)[1] for ln in lines if ln.startswith("sharing_driver=")), "-")
        logging = next((ln.split("=", 1)[1] for ln in lines if ln.startswith("logging_driver=")), "-")
        return (
            f"Durum özeti ({project.code}):\n"
            f"- Sürüm: {version}\n"
            f"- Sharing driver: {sharing}\n"
            f"- Logging driver: {logging}\n\n"
            f"Detay:\n{out}"
        )
    return f"Durum komutu hata verdi (exit={code}).\n\n{out}"


def cmd_quick_test(project: ProjectDef) -> str:
    if project.code != "oc":
        return f"[test_hizli:{project.code}] unsupported project"
    code, out = run_wrapper(project, "scripts/run-quick-test.sh", timeout_sec=420)
    if code == 0:
        return f"Hızlı test tamamlandı ve geçti.\n\n{out}"
    return f"Hızlı testte hata var (exit={code}).\n\n{out}"


def cmd_master(project: ProjectDef) -> str:
    if project.code != "oc":
        return f"[master:{project.code}] unsupported project"
    code, out = run_wrapper(project, "scripts/tg-master.sh", timeout_sec=300)
    if code == 0:
        return f"Master backup başarıyla alındı.\n\n{out}"
    return f"Master backup başarısız (exit={code}).\n\n{out}"


def cmd_log(project: ProjectDef) -> str:
    if project.code != "oc":
        return f"[log:{project.code}] unsupported project"
    code, out = run_wrapper(project, "scripts/tg-log.sh", timeout_sec=45)
    if code == 0:
        return f"Son loglar:\n\n{out}"
    return f"Log komutu hata verdi (exit={code}).\n\n{out}"


def cmd_live_audit(project: ProjectDef) -> str:
    if project.code != "oc":
        return f"[canli_denetim:{project.code}] unsupported project"
    code, out = run_wrapper(project, "scripts/tg-live-audit.sh", timeout_sec=900)
    if code == 0:
        return f"Canlı denetim tamamlandı.\n\n{out}"
    return f"Canlı denetimde hata var (exit={code}).\n\n{out}"


def execute_action(project: ProjectDef, action: str) -> str:
    if action == "status":
        return cmd_status(project)
    if action == "quick_test":
        return cmd_quick_test(project)
    if action == "master":
        return cmd_master(project)
    if action == "log":
        return cmd_log(project)
    if action == "live_audit":
        return cmd_live_audit(project)
    return "Bu aksiyon desteklenmiyor."


def detect_intent(raw: str) -> str:
    text = normalize_text(raw)

    if text in {"durum", "status"}:
        return "status"
    if text in {"test hizli", "hizli test", "quick test", "quicktest", "test"}:
        return "quick_test"
    if text in {"master", "master al", "master yedek", "master yedek al"}:
        return "master"
    if text in {"log", "loglar", "logs"}:
        return "log"
    if text in {"canli denetim", "live audit", "audit"}:
        return "live_audit"
    if re.search(r"\b(projeler|projects)\b", text):
        return "projects"
    if text.startswith("proje "):
        return "set_project"

    if any(
        key in text
        for key in [
            "ne durumda",
            "sistem durumu",
            "sistem su an",
            "stabil",
            "calisiyor mu",
            "saglik",
            "sorun var mi",
            "iyi mi",
            "ayakta mi",
        ]
    ):
        return "status"
    if any(key in text for key in ["hizli test", "test calistir", "test et", "test kos"]):
        return "quick_test"
    if any(key in text for key in ["canli denetim", "canli audit", "http audit", "ops audit", "canli kontrol"]):
        return "live_audit"
    if any(key in text for key in ["master yedek", "master al", "yedek al"]):
        return "master"
    if any(key in text for key in ["hata", "log", "son log"]):
        return "log"

    return ""


def build_llm_messages(state: dict[str, Any], chat_id: int, project: ProjectDef, user_text: str) -> list[dict[str, str]]:
    system_prompt = (
        "Sen Bitaxe OC asistanisin. Turkce, dogal, kisa ve net cevap ver.\n"
        "Asla komut listesi dayatma.\n"
        "Eger kullanici su operasyonlardan birini istiyorsa ACTION satiri ver:\n"
        "- status, quick_test, live_audit, master, log\n"
        "Operasyon istemiyorsa ACTION:none ver.\n"
        "Cevap formati zorunlu:\n"
        "ACTION:<none|status|quick_test|live_audit|master|log>\n"
        "<kullaniciya dogal cevap>\n"
        f"Aktif proje: {project.code}"
    )
    msgs: list[dict[str, str]] = [{"role": "system", "content": system_prompt}]
    msgs.extend(history_get(state, chat_id))
    msgs.append({"role": "user", "content": (user_text or "").strip()[:1200]})
    return msgs


def call_openai_compatible(url: str, model: str, messages: list[dict[str, str]], api_key: str = "") -> str:
    headers: dict[str, str] = {}
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"
    payload = {
        "model": model,
        "messages": messages,
        "temperature": 0.2,
        "max_tokens": 700,
    }
    data = post_json(url, payload, headers=headers, timeout=LLM_TIMEOUT_SEC)
    choices = data.get("choices", [])
    if not isinstance(choices, list) or not choices:
        raise RuntimeError(f"LLM empty choices: {data}")
    message = choices[0].get("message") or {}
    content = str(message.get("content", "")).strip()
    if not content:
        raise RuntimeError("LLM empty content")
    return content


def parse_llm_output(raw: str) -> tuple[str, str]:
    text = (raw or "").strip()
    if not text:
        return "none", ""
    lines = text.replace("\r", "").splitlines()
    action = "none"
    action_line = -1
    for idx, line in enumerate(lines[:8]):
        match = ACTION_PATTERN.match(line.strip())
        if match:
            action = match.group(1).lower()
            action_line = idx
            break
    if action_line >= 0:
        reply = "\n".join(lines[action_line + 1 :]).strip()
    else:
        reply = text
    if action not in ACTION_SET:
        action = "none"
    return action, reply


def llm_reply(state: dict[str, Any], chat_id: int, project: ProjectDef, user_text: str) -> tuple[str, str]:
    messages = build_llm_messages(state, chat_id, project, user_text)
    if LLM_PROVIDER == "openai":
        if not OPENAI_API_KEY:
            raise RuntimeError("OPENAI_API_KEY missing")
        raw = call_openai_compatible(OPENAI_URL, OPENAI_MODEL, messages, api_key=OPENAI_API_KEY)
    else:
        raw = call_openai_compatible(POLLINATIONS_URL, POLLINATIONS_MODEL, messages, api_key="")

    action, reply = parse_llm_output(raw)
    log_event(
        "llm",
        {
            "chat_id": chat_id,
            "provider": LLM_PROVIDER,
            "action": action,
            "reply_preview": reply[:220],
        },
    )
    return action, reply


def handle_action(chat_id: int, project: ProjectDef, action: str) -> str:
    if action not in ACTION_SET or action == "none":
        return ""
    if action in {"quick_test", "live_audit", "master"}:
        send_message(chat_id, "İşlem başlatıldı, tamamlanınca çıktıyı gönderiyorum.")
    result = execute_action(project, action)
    send_message(chat_id, result)
    return result


def handle_message(state: dict[str, Any], chat_id: int, text: str) -> None:
    raw = (text or "").strip()
    lower = normalize_text(raw)
    project = get_chat_project(state, chat_id)

    if lower in {"/start", "/help", "help", "yardim", "yardim."}:
        send_message(chat_id, help_text())
        return

    log_event(
        "incoming",
        {
            "chat_id": chat_id,
            "text": raw[:400],
            "project": project.code,
        },
    )

    intent = detect_intent(raw)
    if intent == "projects":
        lines = ["Projeler:"]
        for code in sorted(PROJECTS):
            marker = "*" if code == project.code else "-"
            lines.append(f"{marker} {code}")
        send_message(chat_id, "\n".join(lines))
        return

    if intent == "set_project":
        requested = lower.split(" ", 1)[1].strip()
        if set_chat_project(state, chat_id, requested):
            send_message(chat_id, f"Aktif proje: {requested}")
        else:
            send_message(chat_id, f"Geçersiz proje: {requested}")
        return

    # Direct intent takes precedence (fast path).
    if intent in ACTION_SET and intent != "none":
        history_push(state, chat_id, "user", raw)
        result = handle_action(chat_id, project, intent)
        history_push(state, chat_id, "assistant", f"[action:{intent}] {result[:400]}")
        return

    # LLM-first natural mode.
    if LLM_ENABLED:
        history_push(state, chat_id, "user", raw)
        try:
            action, reply = llm_reply(state, chat_id, project, raw)
            # Safety gate: LLM cannot trigger operational action unless intent is operational.
            if action != "none" and not detect_intent(raw):
                log_event("llm_action_blocked", {"chat_id": chat_id, "action": action, "reason": "no_operational_intent"})
                action = "none"

            if reply:
                send_message(chat_id, reply)
                history_push(state, chat_id, "assistant", reply)

            if action != "none":
                result = handle_action(chat_id, project, action)
                history_push(state, chat_id, "assistant", f"[action:{action}] {result[:400]}")
                return

            if not reply:
                send_message(chat_id, "Mesajını aldım, biraz daha net yazarsan hemen yardımcı olurum.")
            return
        except (urllib.error.URLError, TimeoutError, RuntimeError) as exc:
            log_event("llm_error", {"chat_id": chat_id, "error": str(exc)})
            # Fall back to deterministic intent path.

    # Final deterministic fallback.
    if not intent:
        send_message(chat_id, "Mesajı anladım ama net aksiyon çıkaramadım. İstersen sistem durumunu kontrol edebilirim.")
        return

    result = handle_action(chat_id, project, intent)
    history_push(state, chat_id, "assistant", f"[fallback:{intent}] {result[:400]}")


def poll_loop() -> None:
    state = load_state()
    offset = int(state.get("offset", 0))

    while True:
        try:
            data = api_call(
                "getUpdates",
                {
                    "timeout": str(POLL_TIMEOUT_SEC),
                    "offset": str(offset),
                    "allowed_updates": json.dumps(["message"]),
                },
                timeout=POLL_TIMEOUT_SEC + 10,
            )
            updates = data.get("result", [])
            if not isinstance(updates, list):
                updates = []

            touched = False
            for update in updates:
                if not isinstance(update, dict):
                    continue
                update_id = int(update.get("update_id", 0))
                if update_id >= offset:
                    offset = update_id + 1
                    state["offset"] = offset
                    touched = True

                message = update.get("message")
                if not isinstance(message, dict):
                    continue
                chat = message.get("chat") or {}
                chat_id = int(chat.get("id", 0))
                if chat_id not in ALLOWED_CHAT_IDS:
                    log_event("blocked_chat", {"chat_id": chat_id, "update_id": update_id})
                    continue
                text = str(message.get("text", "") or "")
                if not text:
                    continue
                handle_message(state, chat_id, text)
                touched = True

            if touched:
                save_state(state)
        except KeyboardInterrupt:
            break
        except Exception as exc:
            print(f"[tg-bridge] error: {exc}", file=sys.stderr)
            time.sleep(2)


if __name__ == "__main__":
    print("tg-bridge started")
    poll_loop()
