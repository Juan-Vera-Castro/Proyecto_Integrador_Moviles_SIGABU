# MinIO compilado desde su código oficial: el proyecto se archivó y ya no publica imágenes ni binarios.
# RELEASE.2025-10-15T17-29-55Z es la última versión y trae el arreglo de GHSA-jjjj-jwhf-8rgr.
FROM golang:1.25-alpine AS build
# -p=2 limita la memoria: con todos los núcleos la compilación agota la RAM de equipos pequeños
ENV CGO_ENABLED=0 GOBIN=/out GOFLAGS=-p=2 GOMAXPROCS=2
RUN go install github.com/minio/minio@RELEASE.2025-10-15T17-29-55Z \
    && go install github.com/minio/mc@RELEASE.2025-08-13T08-35-41Z

FROM alpine:3.22
RUN apk add --no-cache ca-certificates \
    && adduser -D minio \
    && mkdir /data \
    && chown minio:minio /data
COPY --from=build /out/minio /out/mc /usr/local/bin/
USER minio
EXPOSE 9000 9001
ENTRYPOINT ["minio"]
CMD ["server", "/data", "--console-address", ":9001"]
