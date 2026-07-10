# Network Discovery & IP2WHOIS

**IP Subnets** → **Search** → **Network Discovery** scans an IPv4 range (up to 255 addresses) using TCP connect probes (no shell/`exec`). Responding hosts can be added to the **IP Addresses** inventory when they match a company subnet.

## Optional: hosted domains (IP2WHOIS)

When `IP2WHOIS_API_KEY` is configured, each live host is looked up via the IP2WHOIS **Hosted Domains** API:

```bash
curl "https://domains.ip2whois.com/domains?ip=8.8.8.8&key=YOUR_KEY"
```

The scan log and results table show returned domain names. On **Add to inventory**, the first domain may be used as the IP row `hostname` when equipment has no hostname.

## Configure the API key

1. Copy `.env.example` to `.env` in the project root.
2. Set your key (do not commit `.env`):

```env
IP2WHOIS_API_KEY=your_license_key_here
```

3. Restart Apache/PHP so `config/config.php` reloads environment values.

Alternatively set a server environment variable (Apache example):

```apache
SetEnv IP2WHOIS_API_KEY your_license_key_here
```

**Alias:** `ITM_IP2WHOIS_API_KEY` is also accepted.

If the key is missing, discovery still runs; IP2WHOIS steps are skipped and noted in the activity log.

Register / plans: [IP2WHOIS](https://www.ip2whois.com/register) — free tier limits apply (domains per query depends on plan).

## Related documentation

- [Modules Overview](Modules) — IPAM module summary
- [Security & Audits](Security) — secrets and environment variables
