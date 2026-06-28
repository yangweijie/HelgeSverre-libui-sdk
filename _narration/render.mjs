import puppeteer from 'puppeteer-core';
import { createServer } from 'http';
import { readFileSync, existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { execSync, spawnSync } from 'child_process';

const __dirname = dirname(fileURLToPath(import.meta.url));
const NAR_DIR = __dirname;
const CHROME = '/Users/jay/Library/Caches/ms-playwright/chromium-1223/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';

const server = createServer((req, res) => {
  const fp = join(NAR_DIR, req.url === '/' ? 'composition.html' : req.url);
  if (!existsSync(fp)) { res.writeHead(404); res.end(); return; }
  res.writeHead(200, { 'Content-Type': fp.endsWith('.html')?'text/html':'text/css' });
  res.end(readFileSync(fp));
});
await new Promise(r => server.listen(0, r));
const port = server.address().port;
const url = `http://localhost:${port}/`;
console.log('Server:', url);

const browser = await puppeteer.launch({
  executablePath: CHROME, headless: true,
  args: ['--no-sandbox', '--disable-gpu']
});
const page = await browser.newPage();
await page.setViewport({ width: 1920, height: 1080 });
await page.goto(url, { waitUntil: 'load' });
await new Promise(r => setTimeout(r, 300));

const totalDur = await page.evaluate(() => window.__totalDuration);
console.log('Duration:', totalDur, 's');

const fps = 30;
const totalFrames = Math.ceil(totalDur * fps) + fps;
const fd = join(NAR_DIR, 'frames');
mkdirSync(fd, { recursive: true });
const step = Math.max(1, Math.floor(totalFrames / 20));

console.log('Rendering', totalFrames, 'frames...');
for (let i = 0; i < totalFrames; i++) {
  await page.evaluate(t => window.setTime(t), i / fps);
  await page.screenshot({ path: join(fd, `f${String(i).padStart(5,'0')}.png`), type: 'png' });
  if (i % step === 0) process.stdout.write(`  ${Math.round(i/totalFrames*100)}%\r`);
}
process.stdout.write('  100%\n');
await browser.close();
server.close();

console.log('Encoding...');
const vo = join(NAR_DIR, 'voiceover.mp3');
const out = join(NAR_DIR, 'ui2-intro.mp4');
spawnSync('ffmpeg', [
  '-y','-framerate',String(fps), '-i',join(fd,'f%05d.png'),
  '-i',vo, '-c:v','libx264','-preset','medium','-crf','18',
  '-pix_fmt','yuv420p','-c:a','aac','-b:a','192k','-shortest',out
], { stdio:'pipe' });

console.log(execSync(`ffprobe -v quiet -show_entries format=duration,size -of csv=p=0 "${out}"`).toString().trim());
execSync(`rm -rf "${fd}"`);
console.log('Done:', out);
