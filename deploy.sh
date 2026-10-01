#!/bin/sh
# Ship the committed tree to the server and rebuild the stack there:
#
#     ./deploy.sh root@192.168.250.124
#
# The server keeps its own /opt/inkspire/.env (see compose.yaml); everything
# else in that directory is replaced, so a file deleted here is gone there.
set -eu

host=${1:?usage: deploy.sh user@host}

git archive HEAD | ssh "$host" '
    set -eu
    mkdir -p /opt/inkspire && cd /opt/inkspire
    find . -mindepth 1 -maxdepth 1 ! -name .env -exec rm -rf {} +
    tar -x
    docker compose up -d --build --remove-orphans
    docker system prune -f
'
