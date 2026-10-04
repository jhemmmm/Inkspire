---
quick_id: 261001-npv
mode: quick
status: approved
---

# Quick Task 261001-npv: Dockerize Inkspire and deploy it behind a Cloudflare tunnel

Goal: the app runs in Docker on the shop's server (`192.168.250.124`, Ubuntu 22.04, unprivileged Proxmox LXC, 1 GB RAM) and is reachable from the internet through a Cloudflare tunnel, with a one-command redeploy.

Planned and executed inline by the orchestrator, not by planner/executor agents: the work needs the server password and the tunnel token, and neither may be written into a prompt or a planning file that gets committed to a public repository.

## Findings that shape the plan

1. **No local Docker access** (the dev user is not in the `docker` group), so the image is built on the server. `git archive HEAD` ships the committed tree; the server needs no registry.
2. **Bridged containers cannot start in this LXC.** `runc` 1.3.4 fails with `open sysctl net.ipv4.ip_unprivileged_port_start file: reopen fd 8: permission denied`, the known conflict between the runc CVE-2025-52881 fix and the Proxmox LXC AppArmor profile. The fix belongs on the Proxmox host (`lxc-pve` >= 6.0.5-2), which is out of reach from inside the container. Containers on the host network start fine, and so do image builds.
3. **Host networking means every listener must bind to loopback explicitly**, or it lands on the LAN: PHP-FPM (unauthenticated FastCGI on 9000), nginx (8080) and MySQL (3306, plus X Protocol on 33060).
4. **A `cloudflared` systemd service already exists on the server** for a different tunnel (`771a993a…`) and is crash-looping. It is left untouched; the supplied token is for tunnel `be7597f4…`.
5. **The GitHub repository is public**, so the seeded `admin@inkspire.test` password in `DatabaseSeeder` is public knowledge and must not survive on an internet-facing install.
6. The app needs no PHP extension beyond what `serversideup/php:8.4-fpm-nginx` ships (`composer check-platform-reqs --no-dev`), dispatches no queued jobs, and has one scheduled command. No SSR bundle is built, so Inertia renders client-side.

## Decisions

- **D1 — Base image `serversideup/php:8.4-fpm-nginx`.** Non-root, production PHP settings, 100 MB upload limits, and it runs migrations and the Laravel caches on start (`AUTORUN_ENABLED`).
- **D2 — Multi-stage `Dockerfile`**: a build stage adds Node 22 (copied from `node:22-slim`), runs `composer install --no-dev` and `npm run build` (the Wayfinder Vite plugin needs PHP during the build), and the final stage copies the result without `node_modules`.
- **D3 — `compose.yaml` services**: `app`, `scheduler` (`schedule:work`, same image), `mysql` (8.4, tuned for 1 GB), `cloudflared`. All on `network_mode: host`, all bound to `127.0.0.1`.
- **D4 — Secrets live only in `/opt/inkspire/.env` on the server** (`APP_KEY`, `APP_URL`, `DB_PASSWORD`, `TUNNEL_TOKEN`, mode 600). Compose maps each one to the service that needs it, so the tunnel token never enters the app container. Nothing secret is committed.
- **D5 — `QUEUE_CONNECTION=sync`** in production until a queued job exists; no worker container.
- **D6 — Persistent data** in two named volumes: `mysql` and `storage` (`storage/app`, the uploaded design files and avatars). Logs go to stderr.
- **D7 — `deploy.sh user@host`**: ship `git archive HEAD`, rebuild, `up -d`.
- **D8 — After the first seed, replace the admin password with a random one** and hand it to the user.

## Tasks

| #   | Task                       | Files                                         | Verify                                                                                                                                                               |
| --- | -------------------------- | --------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Image and stack definition | `Dockerfile`, `.dockerignore`, `compose.yaml` | Image builds on the server; `docker compose ps` shows all four services healthy/running                                                                              |
| 2   | Redeploy script            | `deploy.sh`                                   | Running it against the server rebuilds and restarts the stack                                                                                                        |
| 3   | Deploy and verify          | server only                                   | `/up` returns 200 on `127.0.0.1:8080`; nothing but SSH and the pre-existing Apache listens on the LAN address; login works; tunnel registers; admin password rotated |

No application code changes, so no Pest tests are added. The stack's own check is the `app` healthcheck against Laravel's `/up` route.
