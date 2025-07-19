(()=>{

async function api(path, method='GET', body=null){
  const opt={method, headers:{'X-WP-Nonce':FS_DATA.nonce}};
  if(body) opt.body=JSON.stringify(body), opt.headers['Content-Type']='application/json';
  const r=await fetch(`${FS_DATA.root}/${path}`,opt);
  return await r.json();
}

/* 渲染墙 */
async function render(){
  const data=await api('suggest');          // ← GET 列表
  const wall=document.querySelector('.fs-wall'); wall.innerHTML='';
  if(!Array.isArray(data)||!data.length){ wall.textContent='暂无建议，点右下角“＋”提交'; return; }

  const max=Math.max(...data.map(p=>+p.meta._votes||0),1);
  data.sort((a,b)=>(+b.meta._votes)-(+a.meta._votes))
      .forEach(p=>{
        const v=+p.meta._votes||0, w=Math.max(10,Math.round(v/max*100));
        wall.insertAdjacentHTML('beforeend',
          `<div class="fs-item" data-id="${p.id}">
             <div class="fs-bar" style="width:${w}%">
               ${p.content.rendered.replace(/<\/?p>/g,'')}
               <span class="fs-count">${v}</span>
             </div>
           </div>`);
      });
}

/* 投票 */
document.addEventListener('click',async e=>{
  const li=e.target.closest('.fs-item'); if(!li) return;
  const rep=await api(`vote/${li.dataset.id}`,'POST');
  if(rep.votes>=0) render(); else alert(rep.message||'投票失败');
});

/* 弹窗提交 */
const add=document.getElementById('fs-add'), pop=document.getElementById('fs-popup');
add.onclick=()=>pop.style.display='flex';
document.getElementById('fs-close').onclick=()=>pop.style.display='none';

document.getElementById('fs-form').addEventListener('submit',async e=>{
  e.preventDefault();
  const fd=new FormData(e.target);
  const rep=await api('suggest','POST',Object.fromEntries(fd.entries()));
  if(rep.id){ alert('已提交！'); pop.style.display='none'; e.target.reset(); render(); }
  else alert(rep.message||'提交失败');
});

/* 首次加载 */
render();

})();
