const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.endsWith('.html'));

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');

    // 1. Quitar universe_bg.js
    content = content.replace(/<script src="universe_bg\.js"><\/script>\n?/g, '');

    // 2. Cambiar fondo y color en dashboard-layout
    // Usualmente: background: transparent; color: white;
    content = content.replace(/background:\s*transparent;/g, 'background: #f8fafc;');
    content = content.replace(/\.dashboard-layout\s*\{[^}]*color:\s*white;/g, (match) => {
        return match.replace('color: white;', 'color: #1e293b;'); // Texto oscuro para fondo claro
    });

    // 3. Tarjetas interactivas (module-card / stat-card / badge-item)
    // Tienen: background: rgba(255, 255, 255, 0.05); o similar
    content = content.replace(/background:\s*rgba\(255,\s*255,\s*255,\s*0\.05\);/g, 'background: #ffffff;');
    content = content.replace(/border:\s*1px\s*solid\s*rgba\(255,255,255,0\.15\);/g, 'border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);');
    
    // Al hacer hover: border-color y background
    content = content.replace(/\.module-card:hover\s*\{[^}]*background:\s*rgba\(255,255,255,0\.1\);/g, (match) => {
        return match.replace('background: rgba(255,255,255,0.1);', 'background: #f1f5f9;');
    });

    // Color del texto principal para que no herede blanco
    // content = content.replace(/color:\s*white;/g, 'color: #1e293b;'); // Peligroso, podría romper la sidebar.
    // Solo forzamos color oscuro en main-content
    content = content.replace(/\.main-content\s*\{/g, '.main-content {\n            color: #1e293b;');
    
    // Asegurarnos que la sidebar siga oscura y con texto blanco
    // content = content.replace(/\.sidebar\s*\{[^}]*\}/g... usually sidebar is fine, it has background: rgba(15, 23, 42, 0.6)
    content = content.replace(/\.sidebar\s*\{[^}]*background:\s*rgba\(15, 23, 42, 0\.6\);/g, (match) => {
        return match.replace('background: rgba(15, 23, 42, 0.6);', 'background: #0f172a;'); // Sólido oscuro
    });

    // Sidebar text fix
    if (!content.includes('.sidebar { color: white; }') && content.includes('.sidebar {') && !content.match(/\.sidebar\s*\{[^}]*color:\s*white;/)) {
         content = content.replace(/\.sidebar\s*\{/, '.sidebar {\n            color: white;');
    }

    // Body backgrounds for standalone pages like index.html, minijuego.html
    content = content.replace(/body\s*\{[^}]*background:\s*transparent;/g, match => match.replace('background: transparent;', 'background: #ffffff;'));

    // Module Card Text
    content = content.replace(/\.module-card\s*\{[^}]*color:\s*white;/g, match => match.replace('color: white;', 'color: #1e293b;'));

    // stat-card and badge-item text
    content = content.replace(/\.stat-card\s*\{[^}]*color:\s*white;/g, match => match.replace('color: white;', 'color: #1e293b;'));
    content = content.replace(/\.badge-item\s*\{[^}]*color:\s*white;/g, match => match.replace('color: white;', 'color: #1e293b;'));

    // Fix module-num contrast
    content = content.replace(/\.module-num\s*\{[^}]*color:\s*var\(--primary-dark\);/g, match => match.replace('color: var(--primary-dark);', 'color: #4f46e5; font-weight: bold;'));

    // For index.html specifically: The hero section text needs dark color and plain backgrounds
    if (file === 'index.html') {
        content = content.replace(/color:\s*white;/g, 'color: #0f172a;');
        content = content.replace('background: transparent;', 'background: #ffffff;');
        content = content.replace('background: #030510;', 'background: #ffffff;');
        content = content.replace('color: #fff;', 'color: #0f172a;');
    }

    fs.writeFileSync(file, content);
});

// Fix global css if necessary
const cssFiles = ['lesson.css', 'index.css'];
cssFiles.forEach(file => {
    if (fs.existsSync(file)) {
        let content = fs.readFileSync(file, 'utf8');
        content = content.replace(/background:\s*transparent;/g, 'background: #ffffff;');
        content = content.replace(/color:\s*#fff/g, 'color: #334155');
        content = content.replace(/color:\s*white;/g, 'color: #334155;');
        
        // Ensure nav and footers have their own clear colors
        content = content.replace(/\.navbar\s*\{[^}]*background:\s*rgba\(0,\s*0,\s*0,\s*0\.8\);/g, 
            match => match.replace('background: rgba(0, 0, 0, 0.8);', 'background: #ffffff; border-bottom: 1px solid #e2e8f0;'));
            
        fs.writeFileSync(file, content);
    }
});

console.log('Cambios a fondo blanco puro y texto oscuro completados de forma global.');
