import http from 'http';
import { spawn, execSync } from 'child_process';
import express from 'express';
import fs from 'fs';

const app = express();
const PORT = 3000;

// Listen immediately on port 3000 so control-plane and supervisor health checks succeed instantly
app.listen(PORT, '0.0.0.0', () => {
  console.log(`[EduManage] Full-Stack School Management System listening on http://0.0.0.0:${PORT}`);
});

// Ensure policy-rc.d is set so apt/dpkg never hangs
try {
  if (!fs.existsSync('/usr/sbin/policy-rc.d')) {
    fs.writeFileSync('/usr/sbin/policy-rc.d', '#!/bin/sh\nexit 101\n', { mode: 0o755 });
  }
} catch (e) {
  // ignore
}

// Self-healing package installer for fresh container environments
function ensureSystemPackages() {
  try {
    execSync('which php >/dev/null 2>&1 && which mariadb >/dev/null 2>&1');
  } catch {
    console.log('[System] Installing PHP and MariaDB binaries...');
    try {
      execSync('DEBIAN_FRONTEND=noninteractive apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" php-cli php-mysql php-sqlite3 php-mbstring mariadb-server mariadb-client', { stdio: 'inherit' });
      console.log('[System] Packages successfully installed.');
    } catch (e) {
      console.warn('[System] Package install warning:', e);
    }
  }
}
ensureSystemPackages();

// Background MariaDB check & auto-start
let dbReady = false;
function checkAndImportDb() {
  try {
    const dbs = execSync('mariadb -u root -e "SHOW DATABASES LIKE \'generation_school\';" 2>/dev/null', { encoding: 'utf-8' });
    if (!dbs.includes('generation_school')) {
      console.log('[Database] generation_school database not found. Creating and importing schema...');
      execSync('mariadb -u root -e "CREATE DATABASE IF NOT EXISTS generation_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"');
      const sqlPath = fs.existsSync('./database/generation_school.sql') 
        ? './database/generation_school.sql' 
        : (fs.existsSync('./database/school_management.sql') ? './database/school_management.sql' : '/database/school_management.sql');
      execSync(`mariadb -u root generation_school < "${sqlPath}"`);
      console.log('[Database] Schema and seed data successfully imported into generation_school.');
    }
  } catch (err) {
    console.warn('[Database] Auto-import check warning:', err);
  }
}

function ensureDatabase() {
  try {
    execSync('mysqladmin ping -u root >/dev/null 2>&1');
    dbReady = true;
    console.log('[Database] MariaDB is active and responding.');
    checkAndImportDb();
  } catch {
    try {
      console.log('[Database] Starting MariaDB server daemon...');
      execSync('mkdir -p /run/mysqld /var/lib/mysql && chown -R mysql:mysql /run/mysqld /var/lib/mysql 2>/dev/null || true');
      execSync('/usr/bin/mysqld_safe --datadir=/var/lib/mysql >/dev/null 2>&1 &');
      
      // Non-blocking ping check
      setTimeout(() => {
        try {
          execSync('mysqladmin ping -u root >/dev/null 2>&1');
          dbReady = true;
          console.log('[Database] MariaDB successfully connected.');
          checkAndImportDb();
        } catch {
          // retry once more
          setTimeout(() => {
            try {
              execSync('mysqladmin ping -u root >/dev/null 2>&1');
              dbReady = true;
              checkAndImportDb();
            } catch (err) {
              console.warn('[Database] Ping check warning:', err);
            }
          }, 2000);
        }
      }, 1000);
    } catch (e) {
      console.warn('[Database] MariaDB start attempt warning:', e);
    }
  }
}
ensureDatabase();

// Background PHP Server on 127.0.0.1:8080
let phpProcess: any = null;
let phpReady = false;
function startPhpServer() {
  try {
    phpProcess = spawn('php', ['-S', '127.0.0.1:8080', 'router.php'], {
      stdio: 'inherit',
      env: {
        ...process.env,
        PHP_CLI_SERVER_WORKERS: '4',
        DB_HOST: '127.0.0.1',
        DB_NAME: 'generation_school',
        DB_USER: 'root',
        DB_PASS: ''
      }
    });
    phpReady = true;

    phpProcess.on('error', (err: any) => {
      console.error('[PHP Process Error]:', err.message);
      phpReady = false;
      setTimeout(startPhpServer, 2000);
    });

    phpProcess.on('exit', (code: number) => {
      console.log(`[PHP] Process exited with code ${code}, restarting...`);
      phpReady = false;
      setTimeout(startPhpServer, 1000);
    });
  } catch (err: any) {
    console.error('[PHP Spawn Failed]:', err.message);
    phpReady = false;
    setTimeout(startPhpServer, 2000);
  }
}
startPhpServer();

// Universal Proxy to PHP Server with iFrame cookie and redirect enhancements
app.use((req, res) => {
  let targetPath = req.originalUrl;
  
  // Normalize /school-management prefix to root for seamless compatibility
  if (targetPath.startsWith('/school-management')) {
    targetPath = targetPath.substring('/school-management'.length) || '/';
  }

  const clientHeaders = { ...req.headers };
  clientHeaders['host'] = '127.0.0.1:8080';
  clientHeaders['x-forwarded-host'] = req.headers.host || `localhost:${PORT}`;
  clientHeaders['x-forwarded-proto'] = 'https'; // Signal HTTPS for cross-site iframe cookies

  const proxyReq = http.request(
    {
      hostname: '127.0.0.1',
      port: 8080,
      path: targetPath,
      method: req.method,
      headers: clientHeaders,
    },
    (proxyRes) => {
      const headers = { ...proxyRes.headers };

      // Ensure all Set-Cookie headers work in cross-origin iframes (AI Studio)
      if (headers['set-cookie']) {
        const rawCookies = Array.isArray(headers['set-cookie'])
          ? headers['set-cookie']
          : [headers['set-cookie']];

        headers['set-cookie'] = rawCookies.map((cookie: string) => {
          let updated = cookie;
          // Strip any conflicting flags
          updated = updated.replace(/;\s*samesite=[^;]+/gi, '');
          updated = updated.replace(/;\s*secure/gi, '');
          // Enforce SameSite=None; Secure; Partitioned
          updated += '; SameSite=None; Secure; Partitioned';
          return updated;
        });
      }

      // Ensure redirects don't point to internal 127.0.0.1:8080
      if (headers['location']) {
        headers['location'] = headers['location']
          .replace(/https?:\/\/127\.0\.0\.1:8080/g, '')
          .replace(/https?:\/\/localhost:8080/g, '');
        if (!headers['location'].startsWith('/')) {
          headers['location'] = '/' + headers['location'];
        }
      }

      res.writeHead(proxyRes.statusCode || 200, headers);
      proxyRes.pipe(res, { end: true });
    }
  );

  proxyReq.on('error', (err) => {
    console.error('[Proxy Error]:', err.message);
    if (!res.headersSent) {
      // Graceful auto-refreshing loading screen if backend is still establishing
      res.status(200).send(`
        <!DOCTYPE html>
        <html>
        <head>
          <meta http-equiv="refresh" content="2">
          <title>Connecting to EduManage...</title>
          <style>
            body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #0f172a; color: #f8fafc; }
            .box { text-align: center; padding: 2rem; border-radius: 1rem; background: #1e293b; border: 1px solid #334155; max-width: 400px; }
            .spinner { border: 3px solid rgba(255,255,255,0.2); border-top-color: #3b82f6; border-radius: 50%; width: 36px; height: 36px; animation: spin 1s linear infinite; margin: 0 auto 1rem; }
            @keyframes spin { to { transform: rotate(360deg); } }
          </style>
        </head>
        <body>
          <div class="box">
            <div class="spinner"></div>
            <h3 style="margin-top:0;">Starting Generation Model School</h3>
            <p style="color:#94a3b8;font-size:0.9rem;">Initializing database and services... please wait a moment.</p>
          </div>
        </body>
        </html>
      `);
    }
  });

  // End request immediately for GET/HEAD, pipe for POST/PUT
  if (req.method === 'GET' || req.method === 'HEAD') {
    proxyReq.end();
  } else {
    req.pipe(proxyReq);
  }
});

process.on('SIGINT', () => {
  if (phpProcess) phpProcess.kill();
  process.exit();
});
process.on('SIGTERM', () => {
  if (phpProcess) phpProcess.kill();
  process.exit();
});
