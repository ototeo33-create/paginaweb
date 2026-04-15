(function() {
    // 1. Setup Canvas
    let canvas = document.getElementById('universe-canvas');
    if (!canvas) {
        canvas = document.createElement('canvas');
        canvas.id = 'universe-canvas';
        canvas.style.position = 'fixed';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.width = '100vw';
        canvas.style.height = '100vh';
        canvas.style.zIndex = '-1';
        canvas.style.pointerEvents = 'none';
        document.body.prepend(canvas);
        document.body.style.backgroundColor = '#030510'; // Fallback
    }

    const ctx = canvas.getContext('2d', { alpha: false });
    let width = window.innerWidth;
    let height = window.innerHeight;
    canvas.width = width;
    canvas.height = height;

    // Window Resize Handling
    window.addEventListener('resize', () => {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width;
        canvas.height = height;
        initSpace();
    });

    // Mouse interactive variables
    let mouse = { x: width/2, y: height/2, vx: 0, vy: 0 };
    let lastMouse = { x: width/2, y: height/2 };
    
    window.addEventListener('mousemove', (e) => {
        lastMouse.x = mouse.x;
        lastMouse.y = mouse.y;
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        mouse.vx = mouse.x - lastMouse.x;
        mouse.vy = mouse.y - lastMouse.y;
    });

    // --- Space Objects ---
    let stars = [];
    let specificConstellGroups = [];
    let shootingStars = [];
    let planets = [];

    class Star {
        constructor(isConstellation = false) {
            this.x = Math.random() * width;
            this.y = Math.random() * height;
            this.baseX = this.x;
            this.baseY = this.y;
            this.z = Math.random() * 2 + 0.1; // Parallax depth
            this.radius = Math.random() * 1.5 + (isConstellation ? 1.5 : 0.2);
            this.alpha = Math.random();
            this.twinkleSpeed = Math.random() * 0.03 + 0.005;
            this.isConstellation = isConstellation;
            this.color = isConstellation ? '#00f3ff' : ((Math.random() > 0.8) ? '#ffccff' : '#ffffff');
            
            // Warp offset
            this.dx = 0;
            this.dy = 0;
        }
        
        update() {
            this.alpha += this.twinkleSpeed;
            if (this.alpha > 1 || this.alpha < 0.2) this.twinkleSpeed *= -1;

            // Parallax movement based on mouse
            let targetDx = (width/2 - mouse.x) * (0.05 / this.z);
            let targetDy = (height/2 - mouse.y) * (0.05 / this.z);
            
            // Gravity Distorsion
            let dx = this.baseX + targetDx - mouse.x;
            let dy = this.baseY + targetDy - mouse.y;
            let dist = Math.sqrt(dx*dx + dy*dy);
            
            let force = 0;
            if (dist < 200) {
                // Repel effect gently
                force = (200 - dist) / 200;
                // Add a swirling effect when mouse moves fast
                targetDx += (dx / dist) * force * 50 - (mouse.vx * force * 0.3);
                targetDy += (dy / dist) * force * 50 - (mouse.vy * force * 0.3);
            }

            // Smooth interpolation
            this.dx += (targetDx - this.dx) * 0.1;
            this.dy += (targetDy - this.dy) * 0.1;
            
            this.x = this.baseX + this.dx;
            this.y = this.baseY + this.dy;

            // Wrap around seamlessly
            if (this.x < -20) this.baseX = width + 20;
            if (this.x > width + 20) this.baseX = -20;
            if (this.y < -20) this.baseY = height + 20;
            if (this.y > height + 20) this.baseY = -20;
        }

        draw() {
            ctx.globalAlpha = this.alpha;
            ctx.fillStyle = this.color;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fill();
            
            if (this.isConstellation) {
                ctx.shadowBlur = 15;
                ctx.shadowColor = this.color;
                ctx.fill();
                ctx.shadowBlur = 0;
            }
        }
    }

    class SpecificConstellation {
        constructor(offsetX, offsetY, scale, points, edges, name) {
            this.stars = [];
            this.edges = edges;
            this.name = name;
            
            points.forEach(p => {
                let s = new Star(true);
                // override position directly
                s.x = offsetX + p[0] * scale;
                s.y = offsetY + p[1] * scale;
                s.baseX = s.x;
                s.baseY = s.y;
                s.color = '#00f3ff'; 
                s.radius = 2.5; // Bigger for focus constellations
                this.stars.push(s);
                stars.push(s); // Add to global stars to get updated
            });
        }
        
        drawLines() {
            ctx.lineWidth = 1.5;
            this.edges.forEach(edge => {
                let s1 = this.stars[edge[0]];
                let s2 = this.stars[edge[1]];
                
                let midX = (s1.x + s2.x)/2;
                let midY = (s1.y + s2.y)/2;
                let mdx = midX - mouse.x;
                let mdy = midY - mouse.y;
                let mdist = Math.sqrt(mdx*mdx + mdy*mdy);
                
                let alpha = 0.6;
                // Fade lines out heavily if Mouse is scattering them
                if(mdist < 150) alpha *= (mdist/150);

                ctx.strokeStyle = `rgba(0, 243, 255, ${alpha})`;
                ctx.beginPath();
                ctx.moveTo(s1.x, s1.y);
                
                // Warp the line physically if mouse is near
                if (mdist < 150) {
                    let warpX = midX + (mdx/mdist)*20;
                    let warpY = midY + (mdy/mdist)*20;
                    ctx.quadraticCurveTo(warpX, warpY, s2.x, s2.y);
                } else {
                    ctx.lineTo(s2.x, s2.y);
                }
                ctx.stroke();
            });

            // Removed Label text as requested
        }
    }

    class ShootingStar {
        constructor() {
            this.reset();
        }
        reset() {
            this.x = Math.random() * width * 1.5;
            this.y = -50;
            this.length = Math.random() * 80 + 40;
            this.speedX = -(Math.random() * 10 + 15);
            this.speedY = Math.random() * 7 + 7;
            this.active = Math.random() > 0.5; // active delay
            this.delay = Math.random() * 400 + 200; // Increased delay to make them appear less often
            this.opacity = 1;
        }
        update() {
            if (this.delay > 0) {
                this.delay--; return;
            }
            this.x += this.speedX;
            this.y += this.speedY;
            if (this.y > height + 100 || this.x < -100) {
                this.reset();
            }
        }
        draw() {
            if (this.delay > 0) return;
            let grad = ctx.createLinearGradient(this.x, this.y, this.x - this.length * (this.speedX/15), this.y - this.length * (this.speedY/15));
            grad.addColorStop(0, "rgba(255, 255, 255, 1)");
            grad.addColorStop(0.2, "rgba(0, 243, 255, 0.8)");
            grad.addColorStop(1, "rgba(255, 255, 255, 0)");
            
            ctx.globalAlpha = 1;
            ctx.strokeStyle = grad;
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(this.x, this.y);
            ctx.lineTo(this.x - this.length * (this.speedX/15), this.y - this.length * (this.speedY/15));
            ctx.stroke();
        }
    }

    class Planet {
        constructor(x, y, radius, colorStops, hasRing, isMoon = false) {
            this.baseX = x; this.baseY = y;
            this.radius = radius;
            this.colorStops = colorStops;
            this.hasRing = hasRing;
            this.isMoon = isMoon;
            this.x = x; this.y = y;
        }
        update() {
            // Very subtle parallax for planet
            let speedMulti = this.isMoon ? 0.03 : 0.015;
            let targetDx = (width/2 - mouse.x) * speedMulti;
            let targetDy = (height/2 - mouse.y) * speedMulti;
            
            // Subtle gravity tilt
            let dx = this.baseX - mouse.x;
            let dy = this.baseY - mouse.y;
            let dist = Math.sqrt(dx*dx + dy*dy);
            if(dist < 300) {
                targetDx += dx * 0.02;
                targetDy += dy * 0.02;
            }
            
            this.x += (this.baseX + targetDx - this.x) * 0.1;
            this.y += (this.baseY + targetDy - this.y) * 0.1;
        }
        draw() {
            ctx.globalAlpha = 1;
            // Draw Planet Body
            let grad = ctx.createRadialGradient(
                this.x - this.radius*0.3, this.y - this.radius*0.3, this.radius*0.1,
                this.x, this.y, this.radius
            );
            this.colorStops.forEach(stop => grad.addColorStop(stop[0], stop[1]));
            
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            
            // Planet atmospheric glow
            ctx.shadowColor = this.colorStops[1][1];
            ctx.shadowBlur = this.isMoon ? 20 : 40;
            ctx.fill();
            ctx.shadowBlur = 0;

            // Draw Rings if requested
            if (this.hasRing) {
                ctx.beginPath();
                ctx.ellipse(this.x, this.y, this.radius * 2.2, this.radius * 0.4, Math.PI / -6, 0, Math.PI * 2);
                ctx.lineWidth = 15;
                ctx.strokeStyle = "rgba(200, 200, 255, 0.2)";
                ctx.stroke();
                
                ctx.beginPath();
                ctx.ellipse(this.x, this.y, this.radius * 1.9, this.radius * 0.25, Math.PI / -6, 0, Math.PI * 2);
                ctx.lineWidth = 4;
                ctx.strokeStyle = "rgba(255, 255, 255, 0.4)";
                ctx.stroke();
            }
        }
    }

    function initSpace() {
        stars = [];
        specificConstellGroups = [];
        shootingStars = [];
        planets = [];

        // Distribute Background Stars
        let starCount = Math.floor((width * height) / 2500); // Responsive amount
        for (let i = 0; i < starCount; i++) {
            stars.push(new Star(false));
        }

        /* ----- URSA MAJOR (Osa Mayor) ----- */
        const ursaPoints = [
            [0, 0.3],    // Alkaid
            [0.6, 0.2],  // Mizar
            [1.2, 0.3],  // Alioth
            [1.7, 0.6],  // Megrez
            [1.6, 1.3],  // Phad
            [2.4, 1.4],  // Merak
            [2.6, 0.5]   // Dubhe
        ];
        const ursaEdges = [[0, 1], [1, 2], [2, 3], [3, 4], [4, 5], [5, 6], [6, 3]];
        // Position it top-rightish
        specificConstellGroups.push(new SpecificConstellation(width * 0.75, height * 0.15, 50, ursaPoints, ursaEdges, "Osa Mayor"));

        /* ----- ORION (Cinturón de Orión) ----- */
        const orionPoints = [
            [0, 0],      // Betelgeuse (left shoulder)
            [1.5, -0.2], // Bellatrix (right shoulder)
            [0.5, 0.9],  // Alnitak (belt left)
            [0.8, 0.8],  // Alnilam (belt middle)
            [1.1, 0.7],  // Mintaka (belt right)
            [0.3, 2.0],  // Saiph (left knee)
            [1.6, 1.8]   // Rigel (right knee)
        ];
        const orionEdges = [
            [0, 1], // shoulders
            [0, 2], [1, 4], // back to belt
            [2, 3], [3, 4], // BELT (Cinturón de Orión)
            [2, 5], [4, 6], // belt to knees
            [5, 6]  // knees
        ];
        // Position it middle-leftish
        specificConstellGroups.push(new SpecificConstellation(width * 0.15, height * 0.5, 65, orionPoints, orionEdges, "Orión"));

        // Shooting stars (Estrellas fugaces)
        for (let i = 0; i < 2; i++) shootingStars.push(new ShootingStar()); // Reduced from 5 to 2

        // Planets
        // 1. Gas Giant with Rings (Bottom right)
        planets.push(new Planet(width * 0.85, height * 0.8, 80, [
            [0, '#88aaff'],
            [0.6, '#3355aa'],
            [1, '#050a1a']
        ], true));

        // 2. Small mysterious moon (Top left)
        planets.push(new Planet(width * 0.15, height * 0.2, 30, [
            [0, '#ffccaa'],
            [0.7, '#884422'],
            [1, '#050a10']
        ], false, true));
        
        // 3. Additional vibrant planet
        planets.push(new Planet(width * 0.3, height * 0.75, 45, [
            [0, '#ff88aa'],
            [0.6, '#aa3355'],
            [1, '#1a050a']
        ], false));
    }

    initSpace();

    // The Render Loop
    function render() {
        // Draw deep space gradient background
        let bgGrad = ctx.createRadialGradient(width/2, height/2, height*0.1, width/2, height/2, width);
        bgGrad.addColorStop(0, "#0a0b18"); // Deep dark core
        bgGrad.addColorStop(0.5, "#060710"); // Violet/Blue black
        bgGrad.addColorStop(1, "#020205"); // Edge space

        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, width, height);

        // Slow decay of mouse velocity
        mouse.vx *= 0.9;
        mouse.vy *= 0.9;

        stars.forEach(s => { s.update(); s.draw(); });
        
        // Draw Specific Constellations
        specificConstellGroups.forEach(cg => cg.drawLines());
        
        planets.forEach(p => { p.update(); p.draw(); });
        
        shootingStars.forEach(ss => { ss.update(); ss.draw(); });

        requestAnimationFrame(render);
    }
    
    // Smooth start
    render();

})();
