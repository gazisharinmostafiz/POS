# Namecheap Demo Limitations

Namecheap shared hosting can be used for a static demo or limited Laravel preview only. It is not suitable for production PosLAB SaaS.

## Why Shared Hosting Is Demo Only

- No reliable long-running queue worker.
- No reliable Laravel scheduler every minute.
- No native WebSocket/Reverb process support.
- Limited Redis availability.
- Limited process control for printer bridge, backups, and workers.
- File and database backups are harder to automate securely.
- Scaling tenant traffic, kitchen displays, and realtime counter updates is not practical.

## Acceptable Demo Use

- Landing page.
- Basic Laravel routes.
- Simple tenant admin preview with low traffic.
- Manual database migration during demo setup.
- External Pusher for realtime if the host allows outbound connections.

## Not Acceptable for Production SaaS

- Restaurant live order taking.
- Kitchen display realtime workflow.
- Counter billing and mixed payment workflow.
- Vendor subscription billing webhooks.
- Printer queue processing.
- Automated encrypted backups.
- Multi-tenant production data.

## Minimum Production Alternative

Use a VPS/cloud provider with:

- Docker or managed runtime.
- PostgreSQL.
- Redis.
- Queue worker process.
- Scheduler cron.
- WebSocket provider or Reverb/Soketi process.
- SSL/HTTPS termination.
- Monitoring and backup automation.

Namecheap DNS can still be used to point domains to the VPS/cloud server, or Cloudflare can manage DNS/security in front of the VPS.
