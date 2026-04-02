FROM python:3.12-slim

# System dependencies (none needed beyond pip packages for this app)
RUN apt-get update && apt-get install -y --no-install-recommends \
        gcc \
        default-libmysqlclient-dev \
        pkg-config \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY bridge.py .

# Run as non-root
RUN useradd -r -u 1001 bridge
USER bridge

CMD ["python", "-u", "bridge.py"]
