#!/usr/bin/env bash
set -Eeuo pipefail

# Bitaxe VPS whitelist refresher
# - Keeps office IP fixed (permanent)
# - Keeps home IP dynamic (single current value, not append)
# - Updates fail2ban + ufw atomically on server

VPS_HOST="${BITAXE_VPS_HOST:-203.0.113.10}"
VPS_USER="${BITAXE_VPS_USER:-root}"
VPS_PASS="${BITAXE_VPS_PASS:-}"
OFFICE_IP="${BITAXE_OFFICE_IP:-198.51.100.23}"
HOME_IP="${BITAXE_HOME_IP:-}"

if [[ -z "${VPS_PASS}" ]]; then
  echo "[error] BITAXE_VPS_PASS tanimli degil."
  echo "Kullanim ornegi:"
  echo "  BITAXE_VPS_PASS='***' ./scripts/refresh-vps-whitelist.sh"
  exit 1
fi

if ! command -v sshpass >/dev/null 2>&1; then
  echo "[error] sshpass bulunamadi. Kurulum: brew install hudochenkov/sshpass/sshpass"
  exit 1
fi

if [[ -z "${HOME_IP}" ]]; then
  for endpoint in \
    "https://api.ipify.org" \
    "https://ifconfig.me/ip" \
    "https://ipv4.icanhazip.com"
  do
    HOME_IP="$(curl -4 -fsS --max-time 4 "${endpoint}" 2>/dev/null | tr -d '[:space:]' || true)"
    if [[ -n "${HOME_IP}" ]]; then
      break
    fi
  done
fi

if [[ -z "${HOME_IP}" ]]; then
  echo "[error] Ev public IP tespit edilemedi."
  exit 1
fi

is_ipv4() {
  local ip="$1"
  [[ "${ip}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || return 1
  IFS='.' read -r o1 o2 o3 o4 <<<"${ip}"
  for o in "${o1}" "${o2}" "${o3}" "${o4}"; do
    (( o >= 0 && o <= 255 )) || return 1
  done
  return 0
}

is_ipv4 "${OFFICE_IP}" || { echo "[error] OFFICE_IP gecersiz: ${OFFICE_IP}"; exit 1; }
is_ipv4 "${HOME_IP}" || { echo "[error] HOME_IP gecersiz: ${HOME_IP}"; exit 1; }

read -r -d '' REMOTE_SCRIPT <<'BASH' || true
#!/usr/bin/env bash
set -Eeuo pipefail

HOME_IP="${1:-}"
OFFICE_IP="${2:-}"

is_ipv4() {
  local ip="$1"
  [[ "$ip" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || return 1
  IFS='.' read -r o1 o2 o3 o4 <<<"$ip"
  for o in "$o1" "$o2" "$o3" "$o4"; do
    (( o >= 0 && o <= 255 )) || return 1
  done
  return 0
}

is_ipv4 "$OFFICE_IP" || { echo "[error] OFFICE_IP gecersiz: $OFFICE_IP"; exit 1; }
is_ipv4 "$HOME_IP" || { echo "[error] HOME_IP gecersiz: $HOME_IP"; exit 1; }

STATE_DIR="/etc/bitaxe-whitelist"
STATE_FILE="${STATE_DIR}/whitelist_home_ip.txt"
mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"

OLD_HOME_IP=""
if [[ -f "$STATE_FILE" ]]; then
  OLD_HOME_IP="$(tr -d ' \t\r\n' < "$STATE_FILE" || true)"
fi

IGNORE_VALUE="127.0.0.1/8 ::1 ${OFFICE_IP} ${HOME_IP}"

upsert_ini_key() {
  local file="$1" section="$2" key="$3" value="$4"
  python3 - "$file" "$section" "$key" "$value" <<'PY'
import pathlib
import re
import sys

file_path = pathlib.Path(sys.argv[1])
section = sys.argv[2]
key = sys.argv[3]
value = sys.argv[4]

if file_path.exists():
    text = file_path.read_text(encoding='utf-8')
else:
    text = ''

lines = text.splitlines()
section_re = re.compile(r'^\s*\[' + re.escape(section) + r'\]\s*$')
key_re = re.compile(r'^\s*' + re.escape(key) + r'\s*=')

sec_start = None
for i, line in enumerate(lines):
    if section_re.match(line):
        sec_start = i
        break

if sec_start is None:
    if lines and lines[-1].strip() != '':
        lines.append('')
    lines.extend([f'[{section}]', f'{key} = {value}'])
else:
    sec_end = len(lines)
    for j in range(sec_start + 1, len(lines)):
        if re.match(r'^\s*\[.*\]\s*$', lines[j]):
            sec_end = j
            break

    key_idx = None
    for j in range(sec_start + 1, sec_end):
        if key_re.match(lines[j]):
            key_idx = j
            break

    new_line = f'{key} = {value}'
    if key_idx is not None:
        lines[key_idx] = new_line
    else:
        lines.insert(sec_end, new_line)

out = "\n".join(lines)
if out and not out.endswith("\n"):
    out += "\n"
file_path.write_text(out, encoding='utf-8')
PY
}

# Home IP degismemisse hicbir islem yapma.
if [[ -n "$OLD_HOME_IP" && "$OLD_HOME_IP" == "$HOME_IP" ]]; then
  echo "[skip] Home IP degismedi: $HOME_IP"
  exit 0
fi

upsert_ini_key /etc/fail2ban/jail.local DEFAULT ignoreip "$IGNORE_VALUE"

# Tum aktif jails icin ignoreip'i jail.local uzerinden zorla uygula.
JAIL_LIST_RAW="$(fail2ban-client status 2>/dev/null | sed -n 's/.*Jail list:[[:space:]]*//p' | head -n1 || true)"
IFS=',' read -r -a ACTIVE_JAILS <<<"${JAIL_LIST_RAW}"
for jail in "${ACTIVE_JAILS[@]}"; do
  jail="$(echo "$jail" | xargs)"
  [[ -n "$jail" ]] || continue
  upsert_ini_key /etc/fail2ban/jail.local "$jail" ignoreip "$IGNORE_VALUE"
done

remove_ufw_rules_for_ip() {
  local ip="$1"
  [[ -z "$ip" ]] && return 0
  local nums
  nums="$(ufw status numbered | awk -v ip="$ip" '$0 ~ ip { n=$1; gsub(/\[/, "", n); gsub(/\]/, "", n); print n }' | sort -rn || true)"
  if [[ -n "$nums" ]]; then
    while IFS= read -r n; do
      [[ -n "$n" ]] || continue
      ufw --force delete "$n" >/dev/null 2>&1 || true
    done <<< "$nums"
  fi
}

remove_ufw_rules_for_ip "$OFFICE_IP"
remove_ufw_rules_for_ip "$HOME_IP"
if [[ -n "$OLD_HOME_IP" && "$OLD_HOME_IP" != "$HOME_IP" ]]; then
  remove_ufw_rules_for_ip "$OLD_HOME_IP"
fi

add_whitelist_rules() {
  local ip="$1"
  ufw insert 1 allow in from "$ip" to any comment 'BITAXE_WHITELIST_GLOBAL' >/dev/null
}

add_whitelist_rules "$OFFICE_IP"
add_whitelist_rules "$HOME_IP"

# Aktif tum jails'ten whitelist IP'leri unban et.
for jail in "${ACTIVE_JAILS[@]}"; do
  jail="$(echo "$jail" | xargs)"
  [[ -n "$jail" ]] || continue
  fail2ban-client set "$jail" unbanip "$OFFICE_IP" >/dev/null 2>&1 || true
  fail2ban-client set "$jail" unbanip "$HOME_IP" >/dev/null 2>&1 || true
  if [[ -n "$OLD_HOME_IP" && "$OLD_HOME_IP" != "$HOME_IP" ]]; then
    fail2ban-client set "$jail" unbanip "$OLD_HOME_IP" >/dev/null 2>&1 || true
  fi
done

fail2ban-client reload >/dev/null

# bitaxe.colortr.com rate-limit whitelist map'ini office + current home IP ile guncelle.
cat > /etc/nginx/conf.d/bitaxe-security.conf <<NGINX
# Bitaxe endpoint rate limiting with whitelist relaxed profile
# Office IP: ${OFFICE_IP}
# Home IP: ${HOME_IP}
map \$remote_addr \$bitaxe_req_key_default {
    default \$binary_remote_addr;
    ${OFFICE_IP} "";
    ${HOME_IP} "";
}
map \$remote_addr \$bitaxe_req_key_office {
    default "";
    ${OFFICE_IP} \$binary_remote_addr;
    ${HOME_IP} \$binary_remote_addr;
}
map \$remote_addr \$bitaxe_conn_key_default {
    default \$binary_remote_addr;
    ${OFFICE_IP} "";
    ${HOME_IP} "";
}
map \$remote_addr \$bitaxe_conn_key_office {
    default "";
    ${OFFICE_IP} \$binary_remote_addr;
    ${HOME_IP} \$binary_remote_addr;
}

limit_req_zone \$bitaxe_req_key_default zone=bitaxe_per_ip:20m rate=8r/s;
limit_req_zone \$bitaxe_req_key_office zone=bitaxe_per_ip_office:20m rate=40r/s;
limit_conn_zone \$bitaxe_conn_key_default zone=bitaxe_conn_limit:20m;
limit_conn_zone \$bitaxe_conn_key_office zone=bitaxe_conn_limit_office:20m;
limit_req_status 429;
limit_conn_status 429;
NGINX

nginx -t >/dev/null
systemctl reload nginx

printf '%s\n' "$HOME_IP" > "$STATE_FILE"
chmod 600 "$STATE_FILE"

echo "[ok] whitelist refresh tamamlandi"
echo "office_ip=${OFFICE_IP}"
echo "home_ip=${HOME_IP}"
echo "old_home_ip=${OLD_HOME_IP}"
echo "[ok] fail2ban ignoreip:"
grep -R "^ignoreip" -n /etc/fail2ban/jail.local /etc/fail2ban/jail.d/*.conf 2>/dev/null || true
echo "[ok] ufw whitelist lines:"
ufw status numbered | awk -v office="$OFFICE_IP" -v home="$HOME_IP" '($0 ~ office || $0 ~ home || $0 ~ /BITAXE_WHITELIST_GLOBAL/) { print }'
echo "[ok] nginx whitelist map:"
sed -n '1,80p' /etc/nginx/conf.d/bitaxe-security.conf
BASH

SSH_OPTS=(
  -o StrictHostKeyChecking=accept-new
  -o PreferredAuthentications=password
  -o PubkeyAuthentication=no
  -o IdentitiesOnly=yes
  -o ConnectTimeout=10
)

echo "[info] VPS: ${VPS_USER}@${VPS_HOST}"
echo "[info] Office IP: ${OFFICE_IP}"
echo "[info] Home IP:   ${HOME_IP}"

sshpass -p "${VPS_PASS}" ssh "${SSH_OPTS[@]}" "${VPS_USER}@${VPS_HOST}" "bash -s -- '${HOME_IP}' '${OFFICE_IP}'" <<<"${REMOTE_SCRIPT}"
