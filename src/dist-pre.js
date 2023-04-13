const fs = require('fs');
const path = require('path');
const packageJson = JSON.parse(fs.readFileSync('package.json')); // replace with your actual package.json file path

const pluginSlug = path.basename(process.cwd());
const mainFile = `${pluginSlug}.php`;

if (!fs.existsSync(mainFile)) {
  throw new Error(`Main PHP file not found in ${process.cwd()}`);
}

let contents = fs.readFileSync(mainFile, 'utf8');
contents = contents.replace(/(Version:\s*)([\d.]+)(-[^\n]+)?/i, `$1${packageJson.version}`);
fs.writeFileSync(mainFile, contents);

console.log( `✔️  Wrote plugin header version to ${mainFile} from package.json` );