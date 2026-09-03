ARG N8N_IMAGE=n8nio/n8n:2.33.4
FROM ${N8N_IMAGE}

USER root

COPY --chown=node:node workflows/knowledge-workflows.json /opt/knowledge-workflows.json
COPY --chown=node:node entrypoint.sh /usr/local/bin/knowledge-n8n-entrypoint

RUN chmod +x /usr/local/bin/knowledge-n8n-entrypoint

USER node

ENTRYPOINT ["/usr/local/bin/knowledge-n8n-entrypoint"]
CMD ["start"]
