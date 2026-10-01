---
quick_id: 261001-npv
phase: quick
plan: 261001-npv
subsystem: infrastructure
status: complete
tags: [docker, compose, cloudflare-tunnel, deployment, mysql, lxc]
key-files:
  created:
    - Dockerfile
    - .dockerignore
    - compose.yaml
    - deploy.sh
  modified: []
commits:
  - 0036767
---

# Quick Task 261001-npv: Dockerize Inkspire and deploy it behind a Cloudflare tunnel

## Outcome

Inkspire runs in Docker on `192.168.250.124` (`/opt/inkspire`). Four services are up: `app` (nginx + PHP-FPM 8.4), `scheduler` (`schedule:work`), `mysql` (8.4) and `cloudflared`. The tunnel `be7597f4…` is connected with four edge connections. `./deploy.sh root@192.168.250.124` redeploys the committed tree.

**One step is still open and is the user's:** the tunnel has no public hostname yet (its remote config is empty, so it answers 503). In the Cloudflare Zero Trust dashboard, add a public hostname on this tunnel with service `http://localhost:8080`, then set `APP_URL` in `/opt/inkspire/.env` to that `https://` hostname and run `docker compose up -d` there. `APP_URL` is `http://localhost:8080` until then.

## What was built

- **`Dockerfile`** — `serversideup/php:8.4-fpm-nginx`. A build stage adds Node 22, runs `composer install --no-dev` and `npm run build`; the final stage copies the app without `node_modules`. No extra PHP extensions were needed.
- **`compose.yaml`** — the four services, two named volumes (`mysql`, `storage` for `storage/app`), and an explicit map from the server's `.env` to each service, so the tunnel token never enters the app container. `QUEUE_CONNECTION=sync`: nothing is queued yet, so there is no worker.
- **`.dockerignore`** — keeps `.env*`, `vendor`, `node_modules`, `public/hot`, dev `bootstrap/cache` and local `storage` contents out of the image.
- **`deploy.sh`** — `git archive HEAD` to the server, replace everything in `/opt/inkspire` except `.env`, `docker compose up -d --build`, prune.

## Deviations from the obvious design, and why

1. **Host networking, everything bound to `127.0.0.1`.** Bridged containers cannot start in this unprivileged Proxmox LXC: `runc` 1.3.4 fails writing `net.ipv4.ip_unprivileged_port_start` (the CVE-2025-52881 fix against the LXC AppArmor profile). The real fix is on the Proxmox host (`lxc-pve` >= 6.0.5-2). Until then the Dockerfile rebinds PHP-FPM and nginx to loopback, MySQL runs with `--bind-address=127.0.0.1 --mysqlx=OFF`, and cloudflared's metrics are pinned to `127.0.0.1:20250` (in a container it defaults to `0.0.0.0`). The nginx `sed` is followed by a `grep` so the build fails if the base image's template changes.
2. **The image is built on the server**, not locally: the dev user has no access to the local Docker socket. The build fits in 1 GB RAM + 1 GB swap (about three minutes cold).
3. **The seeded admin password was replaced.** The repository is public, so `DemoPass123!` is too. After `db:seed`, the admin got a random password, given to the user in the session. Re-running `db:seed` on the server would put the default back.

## Verification

- `docker compose ps`: `app`, `mysql`, `scheduler` healthy; `cloudflared` up, `/ready` reports 4 connections.
- All 43 migrations ran on first start; `php artisan optimize` ran.
- `ss -tlnp`: nginx 8080, PHP-FPM 9000, MySQL 3306 and cloudflared metrics all on `127.0.0.1`. From the LAN, `192.168.250.124:8080` refuses connections.
- `curl` login as the admin: `POST /login` → 302 → `/admin/dashboard` 200, Inertia component `admin/Dashboard`.
- Headless Chrome screenshot of `/login` over an SSH port-forward: page renders with fonts, logos and styles.
- With `X-Forwarded-Proto: https`, asset URLs are generated as `https://`.
- `deploy.sh` run end to end against the server: stack rebuilt and restarted, database and admin password intact afterwards.

Not verified: a request through the public hostname, because none is configured on the tunnel yet.

## Left alone on the server

- `cloudflared.service` (systemd) runs a different, older tunnel (`771a993a…`) and is crash-looping. Suggested: `systemctl disable --now cloudflared`.
- Apache still serves its default page on port 80, and PHP 8.1 packages are installed on the host. Neither is used by this stack.

## Follow-ups

- PayMongo keys, a real mailer (`MAIL_MAILER`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`): add to `/opt/inkspire/.env` when available; mail goes to the log until then.
- Database backups: none are set up. The data lives in the `inkspire_mysql` and `inkspire_storage` volumes.
- The server's disk is 7.8 GB with about 2.7 GB free after a deploy.
