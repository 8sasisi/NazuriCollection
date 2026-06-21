const fs = require('fs');
const path = require('path');

function hexToRgb(hex) {
  hex = hex.replace('#','');
  if (hex.length === 3) hex = hex.split('').map(c => c+c).join('');
  const num = parseInt(hex,16);
  return { r: (num>>16)&255, g: (num>>8)&255, b: num&255 };
}
function rgbToHex(r,g,b){
  return '#'+[r,g,b].map(v=>v.toString(16).padStart(2,'0')).join('');
}
function srgbToLinear(v){ v/=255; return v<=0.03928? v/12.92 : Math.pow((v+0.055)/1.055,2.4); }
function luminance(rgb){ return 0.2126*srgbToLinear(rgb.r)+0.7152*srgbToLinear(rgb.g)+0.0722*srgbToLinear(rgb.b); }
function contrastRatio(hex1, hex2){ const l1=luminance(hexToRgb(hex1)); const l2=luminance(hexToRgb(hex2)); const lighter=Math.max(l1,l2); const darker=Math.min(l1,l2); return (lighter+0.05)/(darker+0.05); }

function rgbToHsl(r,g,b){ r/=255; g/=255; b/=255; const max=Math.max(r,g,b), min=Math.min(r,g,b); let h,s,l=(max+min)/2; if(max===min){h=0;s=0;}else{const d=max-min; s=l>0.5? d/(2-max-min): d/(max+min); switch(max){case r: h=(g-b)/d + (g<b?6:0); break; case g: h=(b-r)/d + 2; break; default: h=(r-g)/d + 4; } h/=6;} return {h:h*360,s:s*100,l:l*100}; }
function hslToRgb(h,s,l){ h/=360; s/=100; l/=100; if(s===0){ const v=Math.round(l*255); return {r:v,g:v,b:v}; } function hue2rgb(p,q,t){ if(t<0) t+=1; if(t>1) t-=1; if(t<1/6) return p+(q-p)*6*t; if(t<1/2) return q; if(t<2/3) return p+(q-p)*(2/3-t)*6; return p; }
 const q = l<0.5? l*(1+s) : l + s - l*s; const p = 2*l - q; const r = hue2rgb(p,q,h+1/3); const g = hue2rgb(p,q,h); const b = hue2rgb(p,q,h-1/3); return {r:Math.round(r*255), g:Math.round(g*255), b:Math.round(b*255)}; }

function suggestAccessibleForeground(fgHex, bgHex, target=4.5){
  // try adjusting lightness up/down minimally
  const fgRgb = hexToRgb(fgHex);
  const hsl = rgbToHsl(fgRgb.r, fgRgb.g, fgRgb.b);
  let best = null;
  for(let delta=0; delta<=100; delta++){
    for(const dir of [1,-1]){
      const l = Math.min(100, Math.max(0, hsl.l + dir*delta));
      const rgb = hslToRgb(hsl.h,hsl.s,l);
      const hex = rgbToHex(rgb.r,rgb.g,rgb.b);
      const cr = contrastRatio(hex, bgHex);
      if(cr>=target){ best = {hex,contrast:cr,delta:dir*delta}; break; }
    }
    if(best) break;
  }
  return best;
}

if(process.argv.length<3){ console.error('Usage: node parse_lighthouse_contrast.js <lighthouse-json> [...]'); process.exit(2); }

for(let i=2;i<process.argv.length;i++){
  const file = process.argv[i];
  try{
    const raw = fs.readFileSync(file,'utf8');
    const data = JSON.parse(raw);
    const audits = data.audits || {};
    const issues = [];
    Object.keys(audits).forEach(k=>{
      const a = audits[k];
      if(!a || a.score===1) return;
      const desc = (a.description||'').toLowerCase();
      if(k.includes('color') || desc.includes('contrast') || (a.id && a.id.includes('color'))){
        const det = a.details || {};
        const items = det.items || det.nodes || [];
        items.forEach(item=>{
          // try known fields
          const fg = item.foregroundColor || (item.node && item.node.foregroundColor) || item.textColor || item.fgColor || null;
          const bg = item.backgroundColor || (item.node && item.node.backgroundColor) || item.bgColor || null;
          const selector = item.selector || (item.node && item.node.selector) || (item.node && item.node.path) || item.path || null;
          const snippet = item.html || (item.node && item.node.snippet) || null;
          if(fg && bg){
            const fgHex = typeof fg === 'string' ? fg : (fg.r ? rgbToHex(fg.r,fg.g,fg.b) : null);
            const bgHex = typeof bg === 'string' ? bg : (bg.r ? rgbToHex(bg.r,bg.g,bg.b) : null);
            if(fgHex && bgHex){
              const current = contrastRatio(fgHex,bgHex);
              const suggestion = suggestAccessibleForeground(fgHex,bgHex,4.5);
              issues.push({selector,snippet,fg:fgHex,bg:bgHex,currentContrast:current.toFixed(2),suggestion});
            } else {
              issues.push({selector,snippet,raw:item, note:'colors present but format unexpected'});
            }
          } else {
            issues.push({selector,snippet,raw:item, note:'no explicit fg/bg captured, inspect node in report'});
          }
        });
      }
    });
    const outPath = path.join(path.dirname(file),'contrast_fixes_'+path.basename(file).replace(/\.[^.]+$/,'' )+'.json');
    fs.writeFileSync(outPath, JSON.stringify({source:file,issues},null,2),'utf8');
    console.log('Parsed',file,'->',outPath,'issues:',issues.length);
  }catch(e){ console.error('Error parsing',file,e.message); }
}
