(function($){
  function initTabs(){
    var tabs=[].slice.call(document.querySelectorAll('.oa-tab[data-tab]'));
    var panels=[].slice.call(document.querySelectorAll('.oa-tab-panel[data-tab-panel]'));
    if(!tabs.length || !panels.length) return;
    function activate(id){
      tabs.forEach(function(tab){
        var active=tab.getAttribute('data-tab')===id;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach(function(panel){
        var active=panel.getAttribute('data-tab-panel')===id;
        panel.classList.toggle('is-active', active);
        panel.hidden=!active;
      });
      if(window.history && window.history.replaceState){
        var hash=id ? '#oa-tab-'+id : '';
        window.history.replaceState({},'',window.location.pathname+window.location.search+hash);
      }
    }
    tabs.forEach(function(tab){
      tab.addEventListener('click', function(){ activate(tab.getAttribute('data-tab')); });
    });
    var selected=(window.location.hash||'').replace('#oa-tab-','');
    var exists=tabs.some(function(tab){ return tab.getAttribute('data-tab')===selected; });
    activate(exists ? selected : tabs[0].getAttribute('data-tab'));
  }

  function toNum(value){
    var n=parseFloat(value);
    return isFinite(n) ? n : 0;
  }

  function escapeHtml(text){
    return String(text || '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function pathFrom(points){
    if(!points.length) return '';
    return points.map(function(p, i){
      return (i===0 ? 'M' : 'L') + p[0].toFixed(1) + ' ' + p[1].toFixed(1);
    }).join(' ');
  }

  function trendChart(el, series){
    if(!el) return;
    var rows=(series || []).map(function(row){
      return {
        day:String(row.day || ''),
        views:toNum(row.views),
        conversions:toNum(row.conversions)
      };
    });
    if(!rows.length){
      el.innerHTML='<div class="oa-chart-empty">No trend data in selected range.</div>';
      return;
    }

    var w=Math.max(340, el.clientWidth || 760);
    var h=Math.max(220, el.clientHeight || 260);
    var m={top:18,right:38,bottom:30,left:44};
    var innerW=Math.max(40,w-m.left-m.right);
    var innerH=Math.max(40,h-m.top-m.bottom);
    var maxViews=Math.max.apply(null, rows.map(function(r){ return r.views; }).concat([1]));
    var maxConv=Math.max.apply(null, rows.map(function(r){ return r.conversions; }).concat([1]));
    var hasConv=rows.some(function(r){ return r.conversions>0; });
    var n=rows.length;
    var gridLines=4;

    function x(i){
      return m.left + ((n<=1 ? 0.5 : (i/(n-1))) * innerW);
    }
    function yViews(v){
      return m.top + innerH - ((v/maxViews) * innerH);
    }
    function yConv(v){
      return m.top + innerH - ((v/maxConv) * innerH);
    }

    var viewPts=rows.map(function(r,i){ return [x(i), yViews(r.views)]; });
    var viewPath=pathFrom(viewPts);
    var areaPath=viewPath + ' L' + x(n-1).toFixed(1) + ' ' + (m.top+innerH).toFixed(1) + ' L' + x(0).toFixed(1) + ' ' + (m.top+innerH).toFixed(1) + ' Z';
    var convPts=rows.map(function(r,i){ return [x(i), yConv(r.conversions)]; });
    var convPath=pathFrom(convPts);

    var gridSvg='';
    for(var i=0;i<=gridLines;i++){
      var y=m.top + ((i/gridLines) * innerH);
      var val=Math.round((maxViews * (gridLines-i))/gridLines);
      gridSvg+='<line x1="'+m.left+'" y1="'+y.toFixed(1)+'" x2="'+(m.left+innerW).toFixed(1)+'" y2="'+y.toFixed(1)+'" stroke="#dce7f6" stroke-dasharray="3 4"/>';
      gridSvg+='<text x="'+(m.left-8)+'" y="'+(y+4).toFixed(1)+'" text-anchor="end" fill="#587298" font-size="11">'+val+'</text>';
    }

    var xTickIdx=[0, Math.floor((n-1)/2), n-1].filter(function(v, i, arr){ return arr.indexOf(v)===i; });
    xTickIdx.forEach(function(i){
      gridSvg+='<text x="'+x(i).toFixed(1)+'" y="'+(h-8)+'" text-anchor="middle" fill="#587298" font-size="11">'+escapeHtml(rows[i].day)+'</text>';
    });

    var convAxis='';
    if(hasConv){
      convAxis+='<text x="'+(w-4)+'" y="'+(m.top+4)+'" text-anchor="end" fill="#8a5a17" font-size="11">'+Math.round(maxConv)+'</text>';
      convAxis+='<text x="'+(w-4)+'" y="'+(m.top+innerH+4).toFixed(1)+'" text-anchor="end" fill="#8a5a17" font-size="11">0</text>';
    }

    var convSvg=hasConv ? '<path d="'+convPath+'" fill="none" stroke="#d97900" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>' : '';
    el.innerHTML=''
      +'<div class="oa-chart-shell">'
      +'<svg viewBox="0 0 '+w+' '+h+'" preserveAspectRatio="none" aria-hidden="true">'
      +'<defs>'
      +'<linearGradient id="oaTrendFill" x1="0" y1="0" x2="0" y2="1">'
      +'<stop offset="0%" stop-color="#1f6feb" stop-opacity=".36"/>'
      +'<stop offset="100%" stop-color="#1f6feb" stop-opacity=".02"/>'
      +'</linearGradient>'
      +'</defs>'
      +gridSvg
      +'<path d="'+areaPath+'" fill="url(#oaTrendFill)"/>'
      +'<path d="'+viewPath+'" fill="none" stroke="#1f6feb" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>'
      +convSvg
      +convAxis
      +'</svg>'
      +'</div>'
      +'<div class="oa-chart-legend">'
      +'<span><i style="background:#1f6feb"></i>Views</span>'
      +(hasConv ? '<span><i style="background:#d97900"></i>Conversions</span>' : '')
      +'</div>';
  }

  function initTrendChart(){
    var el=document.getElementById('oa-trend');
    if(!el) return;
    var series=[];
    try{ series=JSON.parse(el.getAttribute('data-series') || '[]'); }catch(e){}
    var frame=null;
    function render(){
      trendChart(el, series);
      frame=null;
    }
    function requestRender(){
      if(frame!==null) return;
      frame=window.requestAnimationFrame(render);
    }
    render();
    if(typeof ResizeObserver!=='undefined'){
      var ro=new ResizeObserver(requestRender);
      ro.observe(el);
    }else{
      window.addEventListener('resize', requestRender);
    }
  }

  function csvEscape(value){
    var s=value===null || typeof value==='undefined' ? '' : String(value);
    if(/[",\n]/.test(s)) return '"'+s.replace(/"/g,'""')+'"';
    return s;
  }

  function rowsToCsv(rows){
    if(!rows || !rows.length) return '';
    var headers=Object.keys(rows[0]);
    var lines=[headers.join(',')];
    rows.forEach(function(row){
      lines.push(headers.map(function(key){ return csvEscape(row[key]); }).join(','));
    });
    return lines.join('\n');
  }

  function downloadCsv(filename, csvText){
    var blob=new Blob([csvText], {type:'text/csv;charset=utf-8;'});
    var url=URL.createObjectURL(blob);
    var a=document.createElement('a');
    a.href=url;
    a.download=filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function copyToClipboard(text){
    if(navigator.clipboard && navigator.clipboard.writeText){
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function(resolve, reject){
      try{
        var t=document.createElement('textarea');
        t.value=text;
        t.style.position='fixed';
        t.style.opacity='0';
        document.body.appendChild(t);
        t.focus();
        t.select();
        document.execCommand('copy');
        document.body.removeChild(t);
        resolve();
      }catch(err){ reject(err); }
    });
  }

  function fetchExportRows(type, from, to){
    var cfg=window.ordelixAnalyticsAdminCfg || {};
    var restBase=cfg.restBase || '';
    var nonce=cfg.restNonce || '';
    if(!restBase || !nonce) return Promise.reject(new Error('Missing REST config'));
    var url=restBase+'export?type='+encodeURIComponent(type)+'&from='+encodeURIComponent(from || '')+'&to='+encodeURIComponent(to || '');
    return fetch(url, {
      method:'GET',
      credentials:'same-origin',
      headers:{'X-WP-Nonce':nonce}
    })
    .then(function(res){ return res.json(); })
    .then(function(payload){
      if(!payload || !payload.ok || !Array.isArray(payload.rows)) throw new Error('Export failed');
      return payload.rows;
    });
  }

  function buildAllDatasetBundle(datasets){
    var lines=[];
    Object.keys(datasets).forEach(function(type){
      var rows=datasets[type];
      lines.push('# dataset: '+type);
      if(!rows.length){
        lines.push('# empty');
        lines.push('');
        return;
      }
      var headers=Object.keys(rows[0]);
      lines.push(headers.map(csvEscape).join(','));
      rows.forEach(function(row){
        lines.push(headers.map(function(key){ return csvEscape(row[key]); }).join(','));
      });
      lines.push('');
    });
    return lines.join('\n');
  }

  function initCopyLinkButtons(){
    document.querySelectorAll('[data-oa-copy-link]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var form=btn.closest('form');
        if(!form) return;
        var params=new URLSearchParams();
        new FormData(form).forEach(function(value, key){
          if(value!==null && String(value)!=='') params.append(key, String(value));
        });
        var link=window.location.origin+window.location.pathname+(params.toString() ? ('?'+params.toString()) : '');
        copyToClipboard(link)
          .then(function(){ btn.textContent='Copied'; setTimeout(function(){ btn.textContent='Copy link'; }, 1200); })
          .catch(function(){ alert('Could not copy link.'); });
      });
    });
  }

  function initCopySqlButtons(){
    document.querySelectorAll('[data-oa-copy-sql]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var payload=btn.getAttribute('data-sql') || '';
        if(!payload) return;
        copyToClipboard(payload)
          .then(function(){
            btn.dataset.originalLabel=btn.dataset.originalLabel || btn.textContent;
            btn.textContent='Copied';
            setTimeout(function(){ btn.textContent=btn.dataset.originalLabel || 'Copy SQL'; }, 1200);
          })
          .catch(function(){ alert('Could not copy SQL.'); });
      });
    });
  }

  function initExportButtons(){
    var cfg=window.ordelixAnalyticsAdminCfg || {};
    if(!cfg.restBase || !cfg.restNonce) return;
    document.querySelectorAll('[data-oa-export]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var type=btn.getAttribute('data-oa-export');
        var from=btn.getAttribute('data-from') || '';
        var to=btn.getAttribute('data-to') || '';
        if(!type) return;
        btn.disabled=true;
        btn.dataset.originalLabel=btn.dataset.originalLabel || btn.textContent;
        btn.textContent='Exporting...';
        fetchExportRows(type, from, to)
        .then(function(rows){
          if(!rows.length){ alert('No rows available for this range.'); return; }
          var slug=(cfg.siteSlug || 'ordelix').replace(/[^a-z0-9-]/g,'');
          var filename=[slug,type,from,to].filter(Boolean).join('_')+'.csv';
          downloadCsv(filename, rowsToCsv(rows));
        })
        .catch(function(){ alert('Could not export CSV. Please try again.'); })
        .finally(function(){
          btn.disabled=false;
          btn.textContent=btn.dataset.originalLabel || 'Export CSV';
        });
      });
    });

    document.querySelectorAll('[data-oa-export-all]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var from=btn.getAttribute('data-from') || '';
        var to=btn.getAttribute('data-to') || '';
        var types=(btn.getAttribute('data-oa-export-all') || '').split(',').map(function(v){ return v.trim(); }).filter(Boolean);
        if(!types.length) return;
        btn.disabled=true;
        btn.dataset.originalLabel=btn.dataset.originalLabel || btn.textContent;
        btn.textContent='Exporting all...';
        Promise.all(types.map(function(type){
          return fetchExportRows(type, from, to).then(function(rows){ return {type:type, rows:rows}; });
        }))
        .then(function(results){
          var data={};
          results.forEach(function(item){ data[item.type]=item.rows; });
          var slug=(cfg.siteSlug || 'ordelix').replace(/[^a-z0-9-]/g,'');
          var filename=[slug,'all-datasets',from,to].filter(Boolean).join('_')+'.csv';
          downloadCsv(filename, buildAllDatasetBundle(data));
        })
        .catch(function(){ alert('Could not export all datasets. Please try again.'); })
        .finally(function(){
          btn.disabled=false;
          btn.textContent=btn.dataset.originalLabel || 'Export all';
        });
      });
    });
  }

  function hideThirdPartyNotices(){
    var body=document.body;
    if(!body) return;
    var isOaPage=body.classList.contains('toplevel_page_ordelix-analytics')
      || [].slice.call(body.classList).some(function(cls){
        return cls.indexOf('ordelix-analytics_page_ordelix-analytics-')===0;
      });
    if(!isOaPage) return;
    document.querySelectorAll('#wpbody-content .notice, #wpbody-content .updated, #wpbody-content .error, #wpbody-content .update-nag')
      .forEach(function(node){
        if(node.classList.contains('oa-notice')) return;
        if(node.closest('.oa-wrap')) return;
        node.style.display='none';
        node.setAttribute('aria-hidden','true');
      });
  }

  function stepControlsHtml(){
    return '<div class="oa-step-controls">'
      +'<button type="button" class="button oa-icon-btn oa-step-handle" title="Drag to reorder" aria-label="Drag to reorder">::</button>'
      +'<button type="button" class="button oa-icon-btn oa-step-remove" title="Remove step" aria-label="Remove step">x</button>'
      +'</div>';
  }

  function stepHtml(){
    return '<span class="oa-step-index">#</span>'
      +'<select name="step_type[]"><option value="page">Page</option><option value="event">Event</option></select>'
      +'<input type="text" name="step_value[]" placeholder="/path or event_name" required>'
      +'<input type="text" name="step_meta_key[]" placeholder="meta key (optional)">'
      +'<input type="text" name="step_meta_value[]" placeholder="meta value (optional)">'
      +stepControlsHtml();
  }

  function renumberSteps(wrap){
    wrap.querySelectorAll('.oa-step').forEach(function(step, idx){
      var chip=step.querySelector('.oa-step-index');
      if(!chip){
        chip=document.createElement('span');
        chip.className='oa-step-index';
        step.insertBefore(chip, step.firstChild);
      }
      chip.textContent=String(idx+1);
    });
  }

  function initFunnelBuilder(){
    var wrap=document.getElementById('oa-steps');
    if(!wrap) return;
    var dragArmedStep=null;
    var draggingStep=null;
    var add=document.getElementById('oa-add-step');
    if(add){
      add.addEventListener('click', function(){
        var div=document.createElement('div');
        div.className='oa-step';
        div.setAttribute('draggable','true');
        div.innerHTML=stepHtml();
        wrap.appendChild(div);
        renumberSteps(wrap);
      });
    }
    wrap.querySelectorAll('.oa-step').forEach(function(step){
      if(!step.querySelector('.oa-step-controls')){
        step.insertAdjacentHTML('beforeend', stepControlsHtml());
      }
    });
    renumberSteps(wrap);
    wrap.querySelectorAll('.oa-step').forEach(function(step){ step.setAttribute('draggable','true'); });
    wrap.addEventListener('mousedown', function(e){
      var handle=e.target && e.target.closest ? e.target.closest('.oa-step-handle') : null;
      dragArmedStep=handle ? handle.closest('.oa-step') : null;
    });
    wrap.addEventListener('mouseup', function(){ dragArmedStep=null; });
    wrap.addEventListener('click', function(e){
      var btn=e.target;
      if(!btn) return;
      var step=btn.closest('.oa-step');
      if(!step) return;
      if(btn.classList.contains('oa-step-remove')){
        if(wrap.querySelectorAll('.oa-step').length<=2){
          alert('Funnels require at least 2 steps.');
          return;
        }
        step.remove();
        renumberSteps(wrap);
      }else if(btn.classList.contains('oa-step-up')){
        var prev=step.previousElementSibling;
        if(prev){ wrap.insertBefore(step, prev); renumberSteps(wrap); }
      }else if(btn.classList.contains('oa-step-down')){
        var next=step.nextElementSibling;
        if(next){ wrap.insertBefore(next, step); renumberSteps(wrap); }
      }
    });
    wrap.addEventListener('dragstart', function(e){
      var step=e.target && e.target.closest ? e.target.closest('.oa-step') : null;
      if(!step || !dragArmedStep || dragArmedStep!==step){ e.preventDefault(); return; }
      draggingStep=step;
      step.classList.add('is-dragging');
      if(e.dataTransfer){
        e.dataTransfer.effectAllowed='move';
        e.dataTransfer.setData('text/plain','oa-step');
      }
    });
    wrap.addEventListener('dragover', function(e){
      if(!draggingStep) return;
      e.preventDefault();
      var target=e.target && e.target.closest ? e.target.closest('.oa-step') : null;
      if(!target || target===draggingStep) return;
      var rect=target.getBoundingClientRect();
      var before=(e.clientY-rect.top) < (rect.height/2);
      wrap.insertBefore(draggingStep, before ? target : target.nextSibling);
    });
    wrap.addEventListener('drop', function(e){
      if(draggingStep) e.preventDefault();
    });
    wrap.addEventListener('dragend', function(){
      if(!draggingStep) return;
      draggingStep.classList.remove('is-dragging');
      draggingStep=null;
      dragArmedStep=null;
      renumberSteps(wrap);
    });
    var form=wrap.closest('form');
    if(form){
      form.addEventListener('submit', function(e){
        var values=[].slice.call(wrap.querySelectorAll('input[name="step_value[]"]')).map(function(input){ return (input.value||'').trim(); });
        var count=values.filter(Boolean).length;
        var existing=form.querySelector('.oa-inline-error');
        if(existing) existing.remove();
        if(count<2){
          e.preventDefault();
          var err=document.createElement('p');
          err.className='oa-inline-error';
          err.textContent='Add at least two funnel steps before creating a funnel.';
          wrap.parentNode.insertBefore(err, wrap.nextSibling);
        }
      });
    }
  }

  $(function(){
    hideThirdPartyNotices();
    initTabs();
    initCopyLinkButtons();
    initCopySqlButtons();
    initExportButtons();
    initTrendChart();
    initFunnelBuilder();
  });
})(jQuery);

