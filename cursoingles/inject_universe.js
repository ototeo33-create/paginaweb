const fs = require('fs');
const path = require('path');

// 1. Remove background: url('universe.svg') from CSS files
const cssFiles = ['lesson.css', 'index.css'];
cssFiles.forEach(file => {
    if (fs.existsSync(file)) {
        let content = fs.readFileSync(file, 'utf8');
        content = content.replace(/background:\s*url\('universe\.svg'\)[^;]+;/g, 'background: transparent;');
        fs.writeFileSync(file, content);
        console.log(`Updated CSS: ${file}`);
    }
});

// 2. Remove from HTML inline styles and inject universe_bg.js
const htmlFiles = fs.readdirSync('.').filter(f => f.endsWith('.html'));
htmlFiles.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    // Remove inline universe.svg backgrounds inside <style> tags
    content = content.replace(/background:\s*url\('universe\.svg'\)[^;]+;/g, 'background: transparent;');
    
    // Some elements might have `background: url('universe.svg') no-repeat center center fixed; background-size: cover;`
    // Wait, the regex replaced the first part, let's also remove background-size if it was specific to the general background.
    // Generally just removing the url is enough to disable the image.

    // Guarantee the background of `.dashboard-layout` or `body` is explicitly transparent
    content = content.replace(/background-size:\s*cover;/g, '');

    // Inject script if not present
    if (!content.includes('universe_bg.js')) {
        content = content.replace('</body>', '<script src="universe_bg.js"></script>\n</body>');
    }

    fs.writeFileSync(file, content);
});

console.log('Successfully injected WebGL universe and removed universe.svg static background.');
