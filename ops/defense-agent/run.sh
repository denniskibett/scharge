#!/bin/sh
# Called by defense-agent.service. Mondays (or MODE=weekly): composer audit + full file rescan + weekly digest.
# Every other day: quick run + daily digest. Settings: /etc/defense-agent.env
set -e
. /etc/defense-agent.env
MODE=${MODE:-$( [ "$(TZ=Africa/Nairobi date +%u)" = 1 ] && echo weekly || echo daily )}
EXTRA=""
[ "$MODE" = weekly ] && EXTRA="--audit --full"
# First run ever: no baseline yet, so everything is read anyway.
exec /usr/bin/python3 /opt/defense-agent/agent.py $EXTRA \
  --digest "$MODE" --mail-to "$DEFENSE_MAIL_TO" \
  --defend "$DEFENSE_DEFEND" --publish "$DEFENSE_PUBLISH"
