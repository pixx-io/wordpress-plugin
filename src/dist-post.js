const AdmZip = require("adm-zip");
const fs = require('fs');
const path = require('path');
const packageJson = JSON.parse(fs.readFileSync('package.json'));
const pluginSlug = path.basename(process.cwd());

const inputFile = `./${pluginSlug}.zip`;
const zip = new AdmZip(inputFile);

// Create a new folder in the zip file with the name of the plugin slug
const baseFolder = `${pluginSlug}/`;
zip.addFile(baseFolder, Buffer.alloc(0));

// Move all the files in the root of the zip file into the new folder
const zipEntries = zip.getEntries();
zipEntries.slice().forEach((entry) => {
    // skip newly added folder and files
    if( entry.entryName.startsWith(baseFolder) ) return;

    zip.addFile(`${baseFolder}${entry.entryName}`, entry.getData(), '', entry.comment);
    zip.deleteFile(entry.entryName);
});

const outputFileName = `${pluginSlug}-v${packageJson.version}.zip`;
const distPath = path.join(process.cwd(), 'dist');
if (!fs.existsSync(distPath)) {
  fs.mkdirSync(distPath);
}

zip.writeZip(path.join(distPath, outputFileName));
fs.unlinkSync(inputFile);

console.log(`\n🚀 Restructured and moved to ${path.join(distPath, outputFileName)}\n`);