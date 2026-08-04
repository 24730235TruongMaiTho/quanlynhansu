
(()=>{const p=document.querySelector(".hr-page");if(!p)return;const q=p.querySelector("[data-filter-search]"),
d=p.querySelector("[data-filter-department]"),s=p.querySelector("[data-filter-status]"),
rows=[...p.querySelectorAll("tbody tr[data-row]")],empty=p.querySelector(".empty-row"),n=v=>(v||"").toString().trim().toLowerCase();
function run(){let c=0;rows.forEach(r=>{const ok=(!n(q?.value)||n(r.dataset.search||r.textContent).includes(n(q.value)))&&
(!n(d?.value)||n(r.dataset.department)===n(d.value))&&(!n(s?.value)||n(r.dataset.status)===n(s.value));r.hidden=!ok;if(ok)c++});
if(empty)empty.style.display=c?"none":"table-row"}[q,d,s].forEach(x=>x&&x.addEventListener(x.tagName==="INPUT"?"input":"change",run));
p.querySelectorAll("[data-clear-filters]").forEach(b=>b.onclick=()=>{if(q)q.value="";if(d)d.value="";if(s)s.value="";run()});
p.querySelectorAll("[data-tab]").forEach(b=>b.onclick=()=>{p.querySelectorAll("[data-tab]").forEach(x=>x.setAttribute("aria-selected",x===b));
if(s){s.value=b.dataset.tab==="all"?"":b.dataset.tab;run()}});
const t=document.querySelector(".toast");let timer;p.querySelectorAll("[data-toast]").forEach(b=>b.onclick=()=>{if(!t)return;t.textContent=b.dataset.toast;t.classList.add("show");
clearTimeout(timer);timer=setTimeout(()=>t.classList.remove("show"),2200)});run()})();
