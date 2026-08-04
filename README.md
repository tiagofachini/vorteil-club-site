# Vorteil Club — Site

One-page estática (HTML/CSS puro, sem dependências externas exceto imagens hospedadas no CDN da Betalabs) reconstruída a partir do site original vorteilclub.com.br.

Não há etapa de build — é um único `index.html`. O `package.json` existe só para dar suporte a preview local e ao deploy automatizado.

## Ambientes

- **GitHub Pages** (staging/backup): publica sozinho a cada push em `main` → https://tiagofachini.github.io/vorteil-club-site/
- **Hostinger** (produção, `vorteilclub.com.br`): publicado via FTPS a cada push em `main`, pelo workflow `.github/workflows/deploy-hostinger.yml`.

## Preview local

```bash
npm install
npm run dev
```

Abre em `http://localhost:5173`.

## Configurar o deploy automático para a Hostinger (uma única vez)

**1. Pegue os dados de FTP na Hostinger:**
hPanel → Sites → Gerenciar → Arquivos → Contas FTP. Anote host, usuário, senha e a pasta de destino (normalmente `/public_html/` ou `/public_html/nome-do-dominio/` se o domínio não for o principal da conta).

**2. Cadastre 4 secrets no repositório do GitHub** (nunca cole essas credenciais no chat — cadastre direto pelo GitHub):

Pelo site: `Settings` → `Secrets and variables` → `Actions` → `New repository secret`, um por um:
- `FTP_HOST`
- `FTP_USER`
- `FTP_PASSWORD`
- `FTP_REMOTE_ROOT` (ex.: `/public_html/`)

Ou pelo terminal, um comando por secret (ele pede o valor de forma segura, sem exibir na tela):
```bash
gh secret set FTP_HOST
gh secret set FTP_USER
gh secret set FTP_PASSWORD
gh secret set FTP_REMOTE_ROOT
```

**3. Pronto.** No próximo push em `main`, a aba **Actions** do repositório mostra o deploy rodando. Dali em diante, toda mudança publicada aqui vai ao ar na Hostinger sozinha, sem upload manual.

## Deploy manual (fallback)

Caso quiera rodar o deploy à mão em vez de esperar o GitHub Actions:

```bash
cp .env.example .env   # preencha com os dados de FTP
npm install
npm run deploy
```
