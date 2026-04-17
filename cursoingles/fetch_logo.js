const https = require('https');
https.get('https://institutointep.edu.co/', (res) => {
  let data = '';
  res.on('data', (chunk) => { data += chunk; });
  res.on('end', () => {
    const matches = data.match(/<img[^>]+src="([^">]+)"/g);
    console.log(matches);
  });
}).on("error", (err) => { console.log("Error: " + err.message); });
