#!/bin/sh
# Prepara uma VM Ubuntu nova para rodar o SisREAd com Docker (docs/deploy.md).
# Uso, na VM, dentro da pasta do repositório:  sudo sh scripts/provisionar-vm.sh
# Pode rodar de novo sem problema: cada passo confere se já foi feito.
set -e

if [ "$(id -u)" -ne 0 ]; then
    echo "Rode com sudo: sudo sh scripts/provisionar-vm.sh" >&2
    exit 1
fi

USUARIO="${SUDO_USER:-$(logname 2>/dev/null || echo root)}"

# 1. Swap de 2 GB. As VMs gratuitas têm 1 GB de RAM: sem swap, o build da imagem (composer)
#    e picos do worker podem matar processos por falta de memória.
if ! swapon --show | grep -q '/swapfile'; then
    echo "Criando swap de 2 GB em /swapfile"
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    sysctl -w vm.swappiness=10
    echo 'vm.swappiness=10' > /etc/sysctl.d/99-sisread.conf
fi

# 2. Atualizações de segurança automáticas do sistema.
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y unattended-upgrades ca-certificates curl sqlite3
dpkg-reconfigure -f noninteractive unattended-upgrades

# 3. Docker Engine + plugin compose, pelo repositório oficial da Docker.
if ! command -v docker >/dev/null 2>&1; then
    echo "Instalando o Docker"
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    . /etc/os-release
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker

# Deixa o usuário da VM usar o docker sem sudo (vale a partir do próximo login).
if [ "$USUARIO" != "root" ]; then
    usermod -aG docker "$USUARIO"
fi

# 4. Logs do Docker com tamanho limitado, para não encher o disco.
if [ ! -f /etc/docker/daemon.json ]; then
    cat > /etc/docker/daemon.json <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "5" }
}
JSON
    systemctl restart docker
fi

echo
echo "Pronto. Saia e entre de novo no SSH para usar 'docker' sem sudo."
echo "Próximo passo: docs/deploy.md, seção 'Primeiro deploy'."
