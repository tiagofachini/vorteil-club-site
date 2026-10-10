# Vorteil Club — Site

One-page estática (HTML/CSS puro, sem dependências externas exceto imagens hospedadas no CDN da Betalabs) reconstruída a partir do site original vorteilclub.com.br.

Não há etapa de build e **o projeto não tem nenhuma dependência de terceiros** (o `package.json` só define o comando `dev`). Isso é proposital: sem pacotes npm não há o que o scanner de vulnerabilidades da Hostinger ou o Dependabot sinalizar, e não há o que atualizar.

## Ambientes

- **GitHub Pages** (staging/backup): publica sozinho a cada push em `main` → https://tiagofachini.github.io/vorteil-club-site/
- **Hostinger** (produção, `vorteilclub.com.br`): publicado via FTPS a cada push em `main`, pelo workflow `.github/workflows/deploy-hostinger.yml`.

## Preview local

```bash
npm run dev
```

Abre em `http://localhost:5173`. Precisa só do Node 18 ou superior, sem `npm install`. O servidor (`scripts/dev-server.js`) usa apenas módulos nativos e escuta somente em `127.0.0.1`.

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

Para rodar o deploy sem fazer um novo push, dispare o mesmo workflow à mão:

```bash
gh workflow run deploy-hostinger.yml
```

Ou pelo site: aba **Actions** → **Deploy to Hostinger** → **Run workflow**.

## Dependências e relatório de vulnerabilidades da Hostinger

O relatório de vulnerabilidades da Hostinger vem do `npm install` + auditoria que a própria hospedagem executa a cada push (ela clona o repositório, não só recebe o FTP). Por isso o `package.json` **não deve declarar dependências**: qualquer pacote adicionado aqui volta a aparecer no relatório e passa a exigir atualização.

Se precisar de uma ferramenta de desenvolvimento, prefira executá-la sob demanda (`npx`) em vez de declará-la no `package.json`.

O workflow do FTP (`deploy-hostinger.yml`) envia só as páginas do site (HTML, CSS, `robots.txt`, `sitemap.xml`). Se o deploy do Git da Hostinger também estiver ativo na mesma pasta, os dois escrevem no mesmo lugar e o FTP pode falhar de forma intermitente (erro `550`). Mantenha só um dos caminhos ativo.
