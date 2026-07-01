FROM node:24-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM nginx:1.27-alpine

WORKDIR /var/www/html

COPY public ./public
COPY --from=frontend /app/public/build ./public/build
COPY deployment/nginx/poslab.conf /etc/nginx/conf.d/default.conf

RUN mkdir -p /var/www/html/storage/app/public \
    && ln -sf /var/www/html/storage/app/public /var/www/html/public/storage
