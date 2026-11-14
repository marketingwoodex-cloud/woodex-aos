(function(){
  const form  = document.getElementById('site-search-form');
  const input = document.getElementById('site-search-input');
  const panel = document.getElementById('search-suggestions');
  const base  = (window.SEARCH_BASE || '/api/search/').replace(/\/?$/, '/');

  if (!form || !input || !panel) return;

  // Style as "mega" panel
  panel.classList.add('mega-suggest');

  let xhr = null;
  let last = '';

  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms);} }
  function esc(s){ return (s||'').replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

  function safeJSON(txt){
    try { return JSON.parse(txt); } catch(e){}
    const s = txt.indexOf('{'), e = txt.lastIndexOf('}');
    if (s>-1 && e>s) { try { return JSON.parse(txt.slice(s,e+1)); } catch(_){ } }
    return null;
  }

  function clear(){ panel.innerHTML=''; panel.classList.remove('show'); }

  function render(data){
    const q = data.query || input.value.trim();
    const terms = Array.isArray(data.terms) ? data.terms : [];
    const prods = Array.isArray(data.products) ? data.products : [];
    const total = data.counts && typeof data.counts.products==='number' ? data.counts.products : 0;

    panel.innerHTML = `
      <div class="mega-inner">
        <div class="col-left">
          <div class="sec-title">Suggestions</div>
          <div class="term-list">
            ${
              terms.length ? terms.map(t => `
                <a class="term-row" href="${t.url}">
                  <span class="term-label">${t.label}</span>
                  <span class="term-count">${t.count}</span>
                </a>
              `).join('') : `
                <div class="empty-note">No suggestions for “${esc(q)}”.</div>
              `
            }
          </div>
          <a class="see-all" href="/search?query=${encodeURIComponent(q)}">See all “${esc(q)}”${total?` (${total})`:''}</a>
        </div>
        <div class="col-right">
          <div class="sec-title">Products</div>
          <div class="prod-list">
            ${
              prods.length ? prods.map(p => `
                <a class="prod-row" href="${p.url}">
                  <img class="prod-img" src="${p.image}" alt="">
                  <div class="prod-text">
                    <div class="prod-brand">${esc(p.brand||'')}</div>
                    <div class="prod-name">${p.label}</div>
                    <div class="prod-price">PKR ${Number(p.price || 0).toLocaleString(undefined,{minimumFractionDigits:0, maximumFractionDigits:2})}</div>
                  </div>
                </a>
              `).join('') : `
                <div class="empty-note small">No products yet for “${esc(q)}”.</div>
              `
            }
          </div>
        </div>
      </div>
    `;
    panel.classList.add('show');
  }

  function query(){
    const q = input.value.trim();
    if (q.length < 2){ clear(); return; }
    if (q === last) { return; }
    last = q;

    if (xhr) xhr.abort();
    xhr = new XMLHttpRequest();
    xhr.open('GET', `${base}suggest.php?q=${encodeURIComponent(q)}`, true);
    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4){
        if (xhr.status === 200){
          const data = safeJSON(xhr.responseText) || {};
          render(data);
        } else {
          clear();
        }
      }
    };
    xhr.send();
  }

  const debounced = debounce(query, 160);
  input.addEventListener('input', debounced);
  input.addEventListener('focus', debounced);

  // Close when clicking away
  document.addEventListener('click', (e)=>{
    if (!form.contains(e.target)) clear();
  });

  form.addEventListener('submit',(e)=>{
    if (!input.value.trim()) e.preventDefault();
  });
})();
