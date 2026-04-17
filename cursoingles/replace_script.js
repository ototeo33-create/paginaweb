const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.endsWith('.html'));

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    // Replace the HTML span logo with the Institutional image logo
    content = content.replace(/Lingua<span>Pro<\/span>/g, '<img src="https://institutointep.edu.co/logointep.png" alt="INTEP" style="height: 45px; vertical-align: middle; filter: drop-shadow(0 0 10px rgba(255,255,255,0.3));">');
    
    // Replace the remaining text occurrences of LinguaPro
    content = content.replace(/LinguaPro/g, 'INTEP');
    
    fs.writeFileSync(file, content);
});

console.log('Replaced successfully on ' + files.length + ' files.');
