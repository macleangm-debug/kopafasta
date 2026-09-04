#!/usr/bin/env bash
# Provision the Kopafasta STAGING droplet only. Never touches production DNS or the live triptz droplet.
set -euo pipefail

SIZE="${STAGING_SIZE:-s-2vcpu-4gb}"
REGION="${STAGING_REGION:-nyc1}"
IMAGE="${STAGING_IMAGE:-ubuntu-24-04-x64}"
NAME="${STAGING_DROPLET_NAME:-kopafasta-staging}"
SSH_PUB="${SSH_PUB:-$HOME/.ssh/kopafasta_server.pub}"

if ! command -v doctl >/dev/null 2>&1; then
  echo "Install doctl and authenticate: brew install doctl && doctl auth init"
  exit 1
fi

if ! doctl account get >/dev/null 2>&1; then
  echo "DigitalOcean API token required. Run: doctl auth init"
  echo "Then re-run this script. Production will not be touched."
  exit 1
fi

if [[ ! -f "$SSH_PUB" ]]; then
  echo "SSH public key not found at $SSH_PUB"
  exit 1
fi

KEY_NAME="kopafasta-staging"
if ! doctl compute ssh-key list --format Name --no-header | grep -qx "$KEY_NAME"; then
  doctl compute ssh-key import "$KEY_NAME" --public-key-file "$SSH_PUB"
fi
KEY_ID="$(doctl compute ssh-key list --format ID,Name --no-header | awk -v n="$KEY_NAME" '$2==n{print $1; exit}')"

echo "==> Creating ${NAME} (${SIZE} ${REGION})"
doctl compute droplet create "$NAME" \
  --region "$REGION" \
  --size "$SIZE" \
  --image "$IMAGE" \
  --ssh-keys "$KEY_ID" \
  --tag-name kopafasta-staging \
  --enable-monitoring \
  --wait

IP="$(doctl compute droplet list --format Name,PublicIPv4 --no-header | awk -v n="$NAME" '$1==n{print $2; exit}')"
echo "STAGING_DROPLET_IP=${IP}"
echo
echo "GoDaddy A record (only this — do not change @ or www):"
echo "Type    Name       Value           TTL"
echo "A       staging    ${IP}           600"
echo
echo "After DNS points here: ssh -i ~/.ssh/kopafasta_server root@${IP}"
echo "Then: ./scripts/bootstrap-staging-server.sh && STAGING_HOST=root@${IP} ./scripts/deploy-staging.sh"
