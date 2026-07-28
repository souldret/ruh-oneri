/* MangaRuhu Request System - Public JS v2.7 */
/* global mrrsData */
(function(){
'use strict';
var cfg=window.mrrsData||{},apiBase=(cfg.api_url||'').replace(/\/$/,''),nonce=cfg.nonce||'',
    PER_PAGE=parseInt(cfg.per_page,10)||20;

/* ── TOAST ── */
var toastEl=null,toastTimer=null;
function toast(msg,type){
  type=type||'success';
  if(!toastEl){toastEl=document.createElement('div');toastEl.className='mrrs-toast';
    toastEl.setAttribute('role','status');toastEl.setAttribute('aria-live','polite');
    document.body.appendChild(toastEl);}
  toastEl.textContent=msg;
  toastEl.className='mrrs-toast mrrs-toast--'+type+' is-visible';
  clearTimeout(toastTimer);
  toastTimer=setTimeout(function(){toastEl.classList.remove('is-visible');},3200);
}

/* ── UTILS ── */
function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function formatDate(ds){if(!ds)return'';var d=new Date(ds);if(isNaN(d.getTime()))return'';return d.toLocaleDateString('tr-TR',{day:'2-digit',month:'short',year:'numeric'});}
function highlight(text,q){
  if(!q||q.length<2)return escHtml(text);
  var s=escHtml(text),qe=q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),re=new RegExp('('+qe+')','gi');
  return s.replace(re,'<mark class="mrrs-highlight">$1</mark>');
}
function getFingerprint(){
  var raw=[navigator.userAgent,navigator.language,screen.width+'x'+screen.height,new Date().getTimezoneOffset()].join('|');
  if(window.crypto&&window.crypto.subtle){
    try{var buf=new TextEncoder().encode(raw);
      return window.crypto.subtle.digest('SHA-256',buf).then(function(h){
        return Array.from(new Uint8Array(h)).map(function(b){return b.toString(16).padStart(2,'0');}).join('');
      }).catch(function(){return Promise.resolve(fbFp(raw));});}
    catch(e){return Promise.resolve(fbFp(raw));}
  }
  return Promise.resolve(fbFp(raw));
}
function fbFp(raw){var h=0;for(var i=0;i<raw.length;i++){h=Math.imul(31,h)+raw.charCodeAt(i)|0;}return'fb'+Math.abs(h).toString(16).padStart(14,'0');}

/* ── STATUS BADGE ── */
var statusMap={
  pending:    {icon:'clock',        label:'Beklemede',        cls:'pending'},
  reviewing:  {icon:'search',       label:'İnceleniyor',      cls:'reviewing'},
  approved:   {icon:'check-circle', label:'Onaylandı',        cls:'approved'},
  rejected:   {icon:'x-circle',     label:'Reddedildi',       cls:'rejected'},
  translating:{icon:'book-open',    label:'Çeviriye Alındı',  cls:'translating'}
};
var ICONS={};
ICONS['clock']='<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
ICONS['search']='<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
ICONS['check-circle']='<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
ICONS['x-circle']='<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
ICONS['book-open']='<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
ICONS['circle']='<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>';
ICONS['thumbs-up']='<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/><path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>';
ICONS['thumbs-down']='<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3H10z"/><path d="M17 2h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/></svg>';
function lucideIcon(n){return ICONS[n]||'';}
function buildStatusBadge(status){
  var s=statusMap[status]||{icon:'circle',label:status,cls:'unknown'};
  return'<span class="mrrs-badge mrrs-badge--'+s.cls+'">'+lucideIcon(s.icon)+escHtml(s.label)+'</span>';
}

/* ── CARD ── */
function buildCard(item,q,uvt){
  var ua=uvt==='up'?' is-active':'',da=uvt==='down'?' is-active':'',
      up=uvt==='up'?'true':'false',dp=uvt==='down'?'true':'false';
  var badge=buildStatusBadge(item.status||'approved');
  var th=highlight(item.title||'',q);
  var dh='';
  if(item.description){
    var d=item.description.length>120?item.description.substring(0,120)+'\u2026':item.description;
    dh='<p class="mrrs-card__desc">'+highlight(d,q)+'</p>';
  }
  var sp=item.source_link?'<a class="mrrs-card__source-link" href="'+escHtml(item.source_link)+'" target="_blank" rel="noopener noreferrer">Kaynak \u2197</a>':'';
  var sub=item.submitter_name?escHtml(item.submitter_name):'Misafir';
  return'<div class="mrrs-card" role="listitem" data-id="'+item.id+'">'
    +'<div class="mrrs-card__votes">'
    +'<button type="button" class="mrrs-vote-btn mrrs-vote-btn--up'+ua+'" data-vote-btn="'+item.id+'" data-vote-type="up" aria-label="Destekle" aria-pressed="'+up+'">'+lucideIcon('thumbs-up')+'<span class="mrrs-vote-count" data-up-count="'+item.id+'">'+(item.up_votes||0)+'</span></button>'
    +'<button type="button" class="mrrs-vote-btn mrrs-vote-btn--down'+da+'" data-vote-btn="'+item.id+'" data-vote-type="down" aria-label="Desteklemiyorum" aria-pressed="'+dp+'">'+lucideIcon('thumbs-down')+'<span class="mrrs-vote-count" data-down-count="'+item.id+'">'+(item.down_votes||0)+'</span></button>'
    +'</div>'
    +'<div class="mrrs-card__body">'
    +'<div class="mrrs-card__title-row"><p class="mrrs-card__title">'+th+'</p>'+badge+'</div>'
    +dh
    +'<div class="mrrs-card__meta">'
    +'<span class="mrrs-card__date">'+escHtml(formatDate(item.created_at))+'</span>'
    +sp
    +'<span class="mrrs-card__submitter">Öneren: <strong>'+sub+'</strong></span>'
    +'</div></div></div>';
}

/* ── LOCAL STORAGE ── */
function gSV(id){try{return localStorage.getItem('mrrs_v_'+id)||null;}catch(e){return null;}}
function sSV(id,t){try{t?localStorage.setItem('mrrs_v_'+id,t):localStorage.removeItem('mrrs_v_'+id);}catch(e){}}

/* ── URL STATE ── */
function getUrlParams(){
  var sp=new URLSearchParams(window.location.search);
  return{
    page:   Math.max(1,parseInt(sp.get('mrrs_page'),10)||1),
    search: sp.get('mrrs_search')||'',
    sort:   sp.get('mrrs_sort')||'most_votes',
    status: sp.get('mrrs_status')||'all'
  };
}
var popstateInProgress=false;
function pushUrlState(st2){
  if(popstateInProgress)return;
  var sp=new URLSearchParams(window.location.search);
  if(st2.page>1)              sp.set('mrrs_page',  String(st2.page));   else sp.delete('mrrs_page');
  if(st2.search)              sp.set('mrrs_search',st2.search);         else sp.delete('mrrs_search');
  if(st2.sort!=='most_votes') sp.set('mrrs_sort',  st2.sort);           else sp.delete('mrrs_sort');
  if(st2.status!=='all')      sp.set('mrrs_status',st2.status);         else sp.delete('mrrs_status');
  var q=sp.toString(),newUrl=window.location.pathname+(q?'?'+q:'');
  var cur=window.location.pathname+window.location.search;
  if(newUrl!==cur){
    window.history.pushState({mrrs:1,page:st2.page,search:st2.search,sort:st2.sort,status:st2.status},'',newUrl);
  }
}

/* ── PAGINATION — goToPage callback parametre olarak aliniyor (scope sorunu yok) ── */
function buildPagination(page,totalPages,pagerEl,onPage){
  pagerEl.innerHTML='';
  if(totalPages<=1)return;
  var isMobile=window.innerWidth<520;
  function mkBtn(label,tp,disabled,active,ariaLbl){
    var b=document.createElement('button');
    b.type='button';
    b.className='mrrs-page-btn'+(active?' is-active':'')+(disabled?' is-disabled':'');
    b.disabled=!!disabled;
    b.innerHTML=label;
    if(ariaLbl)b.setAttribute('aria-label',ariaLbl);
    if(active)b.setAttribute('aria-current','page');
    if(!disabled&&!active){
      b.addEventListener('click',function(){onPage(tp);});
    }
    return b;
  }
  pagerEl.appendChild(mkBtn('\u25c0',page-1,page<=1,false,'Önceki sayfa'));
  if(isMobile){
    var info=document.createElement('span');
    info.className='mrrs-page-mobile-info';info.setAttribute('aria-live','polite');
    info.textContent=page+' / '+totalPages;pagerEl.appendChild(info);
  }else{
    buildPageNums(page,totalPages).forEach(function(n){
      if(n==='...'){
        var el=document.createElement('span');el.className='mrrs-page-ellipsis';
        el.textContent='\u2026';el.setAttribute('aria-hidden','true');pagerEl.appendChild(el);
      }else{
        pagerEl.appendChild(mkBtn(String(n),n,false,n===page,'Sayfa '+n));
      }
    });
  }
  pagerEl.appendChild(mkBtn('\u25b6',page+1,page>=totalPages,false,'Sonraki sayfa'));
}
function buildPageNums(cur,total){
  if(total<=7){var a=[];for(var i=1;i<=total;i++)a.push(i);return a;}
  var p=[1];
  if(cur>3)p.push('...');
  var s=Math.max(2,cur-1),e=Math.min(total-1,cur+1);
  for(var j=s;j<=e;j++)p.push(j);
  if(cur<total-2)p.push('...');
  p.push(total);return p;
}

/* ── BOARD ── */
var board=document.querySelector('[data-mrrs-board]');
if(board){
  var listEl  =board.querySelector('[data-mrrs-list]'),
      emptyEl =board.querySelector('[data-mrrs-empty]'),
      loaderEl=board.querySelector('[data-mrrs-loader]'),
      searchEl=board.querySelector('[data-mrrs-search]'),
      pgWrap  =board.querySelector('[data-mrrs-pagination]'),
      pgInfo  =board.querySelector('[data-mrrs-page-info]'),
      pagerEl =board.querySelector('[data-mrrs-pager]'),
      totalEl =board.querySelector('[data-mrrs-total]');

  var init=getUrlParams();
  var st={page:init.page,search:init.search,sort:init.sort,status:init.status,loading:false,totalPages:1};

  if(searchEl&&st.search)searchEl.value=st.search;

  /* Sort pills */
  var sortPills=board.querySelectorAll('[data-mrrs-sort-pill]');
  sortPills.forEach(function(b){
    b.classList.toggle('is-active',b.getAttribute('data-mrrs-sort-pill')===st.sort);
    b.addEventListener('click',function(){
      if(b.classList.contains('is-active'))return;
      sortPills.forEach(function(x){x.classList.remove('is-active');});
      b.classList.add('is-active');
      st.sort=b.getAttribute('data-mrrs-sort-pill')||'most_votes';
      st.page=1;loadPage();
    });
  });

  /* Status pills */
  var statusPills=board.querySelectorAll('[data-mrrs-status-pill]');
  statusPills.forEach(function(b){
    b.classList.toggle('is-active',b.getAttribute('data-mrrs-status-pill')===st.status);
    b.addEventListener('click',function(){
      if(b.classList.contains('is-active'))return;
      statusPills.forEach(function(x){x.classList.remove('is-active');});
      b.classList.add('is-active');
      st.status=b.getAttribute('data-mrrs-status-pill')||'all';
      st.page=1;loadPage();
    });
  });

  /* Search debounce */
  var sTimer;
  if(searchEl){
    searchEl.addEventListener('input',function(){
      clearTimeout(sTimer);
      sTimer=setTimeout(function(){st.search=searchEl.value.trim();st.page=1;loadPage();},320);
    });
  }

  function setLoading(on){st.loading=on;if(loaderEl)loaderEl.hidden=!on;}
  function showEmpty(on){if(emptyEl)emptyEl.hidden=!on;}

  /* goToPage board scope icinde tanimli — buildPagination'a callback olarak geciliyor */
  function goToPage(p){
    p=parseInt(p,10);
    if(isNaN(p)||p<1||p>st.totalPages||p===st.page)return;
    st.page=p;
    loadPage();
    try{board.scrollIntoView({behavior:'smooth',block:'start'});}catch(e){}
  }

  function loadPage(){
    if(st.loading)return;
    setLoading(true);showEmpty(false);
    if(listEl)listEl.innerHTML='';
    if(pgWrap)pgWrap.hidden=true;
    pushUrlState(st);
    var p=new URLSearchParams({
      search:st.search,sort:st.sort,
      page:String(st.page),per_page:String(PER_PAGE),status:st.status
    });
    fetch(apiBase+'/requests?'+p,{headers:{'X-WP-Nonce':nonce}})
      .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
      .then(function(data){
        if(data.items&&data.items.length){
          data.items.forEach(function(item){
            listEl.insertAdjacentHTML('beforeend',buildCard(item,st.search,gSV(item.id)));
          });
          var total=parseInt(data.total,10)||0;
          var perPage=parseInt(data.per_page,10)||PER_PAGE;
          st.totalPages=data.total_pages
            ?parseInt(data.total_pages,10)
            :Math.max(1,Math.ceil(total/perPage));

          if(totalEl){
            totalEl.textContent=total+' öneri';
            totalEl.hidden=false;
          }
          if(pgInfo&&total>0){
            var from=data.from||(((st.page-1)*perPage)+1);
            var to  =data.to  ||(Math.min(st.page*perPage,total));
            pgInfo.textContent=from+'–'+to+' / '+total+' öneri gösteriliyor';
          }
          /* goToPage callback olarak geciliyor */
          if(pagerEl)buildPagination(st.page,st.totalPages,pagerEl,goToPage);
          if(pgWrap)pgWrap.hidden=(st.totalPages<=1);
        }else{
          showEmpty(true);
          if(pgWrap)pgWrap.hidden=true;
          if(totalEl){totalEl.textContent='0 öneri';totalEl.hidden=false;}
        }
      })
      .catch(function(err){
        console.error('[MRRS] loadPage error:',err);
        showEmpty(true);
      })
      .finally(function(){setLoading(false);});
  }

  /* popstate */
  window.addEventListener('popstate',function(e){
    var state=e.state;
    if(state&&typeof state.mrrs!=='undefined'){
      popstateInProgress=true;
      st.page=Math.max(1,state.page||1);
      st.search=state.search||'';
      st.sort=state.sort||'most_votes';
      st.status=state.status||'all';
      if(searchEl)searchEl.value=st.search;
      sortPills.forEach(function(b){b.classList.toggle('is-active',b.getAttribute('data-mrrs-sort-pill')===st.sort);});
      statusPills.forEach(function(b){b.classList.toggle('is-active',b.getAttribute('data-mrrs-status-pill')===st.status);});
      loadPage();
      popstateInProgress=false;
    }
  });

  /* Vote */
  if(listEl){
    listEl.addEventListener('click',function(e){
      var btn=e.target.closest('[data-vote-btn]');
      if(!btn||btn.disabled||btn.classList.contains('is-active'))return;
      var id=parseInt(btn.getAttribute('data-vote-btn'),10),
          vt=btn.getAttribute('data-vote-type')||'up',
          ab=listEl.querySelectorAll('[data-vote-btn="'+id+'"]');
      ab.forEach(function(b){b.disabled=true;});
      getFingerprint()
        .then(function(fp){
          return fetch(apiBase+'/requests/'+id+'/vote',{
            method:'POST',
            headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
            body:JSON.stringify({vote_type:vt,fingerprint:fp})
          });
        })
        .then(function(r){return r.json();})
        .then(function(data){
          if(typeof data.up_votes!=='undefined'){
            var uel=listEl.querySelector('[data-up-count="'+id+'"]'),
                dl =listEl.querySelector('[data-down-count="'+id+'"]');
            if(uel)uel.textContent=data.up_votes;
            if(dl) dl.textContent=data.down_votes;
            ab.forEach(function(b){
              var it=b.getAttribute('data-vote-type')===(data.vote_type||vt);
              b.classList.toggle('is-active',it&&!!data.voted);
              b.setAttribute('aria-pressed',it&&!!data.voted?'true':'false');
            });
            sSV(id,data.vote_type||null);
            if(data.action==='new')         toast('✓ Oyunuz kaydedildi.','success');
            else if(data.action==='changed')toast('✓ Oyunuz güncellendi.','success');
          }else if(data.message){
            toast(data.message,'error');
          }
        })
        .catch(function(){toast('Bağlantı hatası.','error');})
        .finally(function(){ab.forEach(function(b){b.disabled=false;});});
    });
  }

  loadPage();
}

/* ── FORM TOGGLE ── */
function closeFormPanel(tb,fi){
  fi.style.maxHeight='0';fi.style.opacity='0';
  tb.setAttribute('aria-expanded','false');
  var ic=tb.querySelector('.mrrs-toggle-icon');
  if(ic)ic.style.transform='rotate(0deg)';
}
function openFormPanel(tb,fi){
  fi.style.maxHeight=fi.scrollHeight+'px';fi.style.opacity='1';
  tb.setAttribute('aria-expanded','true');
  var ic=tb.querySelector('.mrrs-toggle-icon');
  if(ic)ic.style.transform='rotate(45deg)';
}

var fw=document.querySelector('.mrrs-form-wrap');
if(fw){
  var tb=fw.querySelector('[data-mrrs-form-toggle]'),fi=fw.querySelector('.mrrs-form-inner');
  if(tb&&fi){
    var exp=false;
    tb.addEventListener('click',function(){
      exp=!exp;
      if(exp)openFormPanel(tb,fi);else closeFormPanel(tb,fi);
    });
    var closeBtn=fw.querySelector('[data-mrrs-form-close]');
    if(closeBtn){
      closeBtn.addEventListener('click',function(){
        exp=false;closeFormPanel(tb,fi);
      });
    }
  }
}

/* ── FORM SUBMIT ── */
var frm=document.querySelector('[data-mrrs-form]');
if(frm){
  var ntc=(frm.closest('.mrrs-form-wrap')||{}).querySelector
    ? frm.closest('.mrrs-form-wrap').querySelector('[data-mrrs-form-notice]')
    : null;
  /* sbtn: data-mrrs-submit attribute'li buton */
  var sbtn=frm.querySelector('[data-mrrs-submit]');
  /* Butonun orijinal label'ini DOM'dan oku, textContent set etme */
  var SUBMIT_ORIG_HTML = sbtn ? sbtn.innerHTML : 'Öneriyi Gönder';
  var SUBMIT_LOADING   = 'Gönderiliyor…';

  function showNotice(msg,type){
    if(!ntc)return;
    ntc.textContent=msg;
    ntc.className='mrrs-form__notice is-'+type;
    ntc.hidden=false;
  }
  function resetSubmitBtn(){
    if(!sbtn)return;
    sbtn.disabled=false;
    sbtn.innerHTML=SUBMIT_ORIG_HTML;
  }

  frm.addEventListener('submit',function(e){
    e.preventDefault();
    if(ntc)ntc.hidden=true;
    if(sbtn){sbtn.disabled=true;sbtn.textContent=SUBMIT_LOADING;}

    var titleEl=frm.querySelector('[name="title"]');
    var sourceEl=frm.querySelector('[name="source_link"]');
    var descEl=frm.querySelector('[name="description"]');
    var hpEl=frm.querySelector('[name="website"]');

    var title=(titleEl?titleEl.value:'').trim();
    var source=sourceEl?sourceEl.value:'';
    var desc=descEl?descEl.value:'';
    var hp=hpEl?hpEl.value:'';

    if(!title){
      showNotice('Seri adı zorunludur.','error');
      resetSubmitBtn();
      return;
    }

    fetch(apiBase+'/requests',{
      method:'POST',
      headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
      body:JSON.stringify({title:title,source_link:source,description:desc,website:hp})
    })
    .then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j};});})
    .then(function(res){
      if(res.ok&&res.j&&res.j.success){
        showNotice(res.j.message||'Öneriniz alındı! Admin onayından sonra yayınlanacak.','success');
        toast('✓ Öneriniz gönderildi.','success');
        frm.reset();
      }else{
        showNotice((res.j&&res.j.message)||'Bir hata oluştu.','error');
      }
    })
    .catch(function(){showNotice('Bağlantı hatası.','error');})
    .finally(function(){resetSubmitBtn();});
  });
}
})();
