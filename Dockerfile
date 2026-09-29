# syntax=docker/dockerfile:1
FROM node:20-alpine AS app
WORKDIR /app

ARG APP_VERSION=1.0.0
ARG APP_REVISION=local
ARG BUILD_DATE

COPY package.json package-lock.json ./
RUN npm ci --omit=dev && npm cache clean --force
COPY . .
RUN node scripts/package-wp-plugin.js \
  && mkdir -p /app/data \
  && chown -R node:node /app

ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=9091
ENV WEBHOST_BASE_PATH=/webhost/snooker
ENV TZ=Europe/Amsterdam
ENV CLUB_TZ=Europe/Amsterdam
ENV APP_VERSION=$APP_VERSION
ENV APP_REVISION=$APP_REVISION
ENV BUILD_DATE=$BUILD_DATE

LABEL org.opencontainers.image.title="webhost-snooker" \
      org.opencontainers.image.description="Snookerclub guest app, admin dashboard and live embeds" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${APP_REVISION}" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.source="https://github.com/AlexVV13/parkData-project"

EXPOSE 9091
USER node
HEALTHCHECK --interval=30s --timeout=4s --start-period=15s --retries=3 \
  CMD node -e "const b=process.env.WEBHOST_BASE_PATH||'/webhost/snooker'; fetch('http://127.0.0.1:'+(process.env.PORT||9091)+b+'/health').then((r)=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

CMD ["node", "server.js"]
