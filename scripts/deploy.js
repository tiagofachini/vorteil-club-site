/**
 * Deploy manual do site estático para a Hostinger via FTP/FTPS.
 * Uso normal: o deploy acontece sozinho via GitHub Actions a cada push em main.
 * Este script é o fallback para rodar o mesmo deploy manualmente: `npm run deploy`.
 */
require('dotenv').config();
const path = require('path');
const FtpDeploy = require('ftp-deploy');

const required = ['FTP_HOST', 'FTP_USER', 'FTP_PASSWORD', 'FTP_REMOTE_ROOT'];
const missing = required.filter((key) => !process.env[key]);

if (missing.length) {
  console.error(`Faltam variáveis de ambiente: ${missing.join(', ')}`);
  console.error('Copie .env.example para .env e preencha com os dados de FTP da Hostinger.');
  process.exit(1);
}

const ftpDeploy = new FtpDeploy();

const config = {
  user: process.env.FTP_USER,
  password: process.env.FTP_PASSWORD,
  host: process.env.FTP_HOST,
  port: process.env.FTP_PORT ? Number(process.env.FTP_PORT) : 21,
  secure: process.env.FTP_SECURE !== 'false',
  localRoot: path.join(__dirname, '..'),
  remoteRoot: process.env.FTP_REMOTE_ROOT,
  include: ['index.html'],
  exclude: [
    '.git/**',
    '.github/**',
    'node_modules/**',
    'scripts/**',
    '.env',
    '.env.example',
    '.gitignore',
    'package.json',
    'package-lock.json',
    'README.md',
  ],
  deleteRemote: false,
  forcePasv: true,
};

ftpDeploy
  .deploy(config)
  .then((res) => console.log('Deploy concluído:', res))
  .catch((err) => {
    console.error('Falha no deploy:', err);
    process.exit(1);
  });
