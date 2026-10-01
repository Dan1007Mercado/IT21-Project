# Trusted proxy deployment

INTSEC resolves client addresses through Laravel's trusted-proxy middleware. It never parses `X-Forwarded-For` in controllers or security services.

Set `INTSEC_TRUSTED_PROXIES` to a comma-separated list of proxy addresses or CIDR ranges owned by the deployment. Leave it empty when Apache/Nginx connects directly to PHP without a separate reverse proxy.

- Apache or Nginx directly exposed: leave the list empty; `REMOTE_ADDR` is the client address.
- Reverse proxy/load balancer: list only the proxy/load-balancer addresses or private CIDRs that connect to INTSEC.
- Multiple proxy hops: every trusted hop must be listed, and edge infrastructure must replace—not append untrusted client forwarding headers.

Never configure `*` for convenience on an internet-accessible deployment. Validate the resolved address in staging before enabling IP blocking or location enrichment.
