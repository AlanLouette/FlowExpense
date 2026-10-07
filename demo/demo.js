'use strict';
const $ = id => document.getElementById(id);
const key = 'flowexpense.public-demo.v1';
const categories = ['Déplacements','Fournitures','Hébergement','Repas'];
const statuses = ['En attente','Validée','Payée'];
const rates = [0,6,12,21];
const euro = value => new Intl.NumberFormat('fr-BE',{style:'currency',currency:'EUR'}).format(value);
const today = () => new Date().toLocaleDateString('en-CA');
const gross = row => Math.round((row.net + Math.round(row.net * row.vat) / 100) * 100) / 100;
function samples() {
 const month = new Date().toISOString().slice(0,7);
 return [
  {id:'DEMO-001',supplier:'Studio Pixel',description:'Abonnement logiciel de création',date:month+'-02',category:'Fournitures',status:'Payée',net:45,vat:21},
  {id:'DEMO-002',supplier:'Café du Parc',description:'Déjeuner de réunion',date:month+'-03',category:'Repas',status:'Validée',net:32,vat:12},
  {id:'DEMO-003',supplier:'Rail Express',description:'Déplacement pour une présentation',date:month+'-04',category:'Déplacements',status:'En attente',net:24,vat:6},
  {id:'DEMO-004',supplier:'Cloud Atelier',description:'Hébergement du site de démonstration',date:month+'-05',category:'Hébergement',status:'Payée',net:59,vat:21},
  {id:'DEMO-005',supplier:'Bureau & Co',description:'Fournitures et accessoires de bureau',date:month+'-06',category:'Fournitures',status:'En attente',net:78.5,vat:21}
 ];
}
function valid(r){return r && typeof r.id==='string' && r.id.length<=80 && typeof r.supplier==='string' && r.supplier.length<=80 && typeof r.description==='string' && r.description.length<=160 && /^\d{4}-\d{2}-\d{2}$/.test(r.date) && categories.includes(r.category) && statuses.includes(r.status) && Number.isFinite(r.net) && r.net>=0 && r.net<=1000000 && rates.includes(r.vat);}
let data=samples();
try{const saved=JSON.parse(localStorage.getItem(key));if(Array.isArray(saved)&&saved.length<=200&&saved.every(valid))data=saved;}catch{}
let messageTimer;
function notify(text){$('message').textContent=text;$('message').hidden=false;clearTimeout(messageTimer);messageTimer=setTimeout(()=>{$('message').hidden=true;},4000);}
function persist(){try{localStorage.setItem(key,JSON.stringify(data));return true;}catch{notify('Stockage indisponible : vos essais restent disponibles jusqu’au rechargement.');return false;}}
function visible(){const q=$('search').value.trim().toLocaleLowerCase('fr');return data.filter(r=>(!q||(r.supplier+' '+r.description+' '+r.id).toLocaleLowerCase('fr').includes(q))&&(!$('category').value||r.category===$('category').value)&&(!$('status').value||r.status===$('status').value));}
function cell(row,text){const td=document.createElement('td');td.textContent=text;row.append(td);return td;}
function render(){
 const rows=visible();$('rows').replaceChildren();
 $('total').textContent=euro(data.reduce((sum,r)=>sum+gross(r),0));$('pending').textContent=euro(data.filter(r=>r.status==='En attente').reduce((sum,r)=>sum+gross(r),0));
 $('count').textContent=rows.length+' dépense'+(rows.length===1?'':'s');$('empty').hidden=rows.length>0;
 for(const r of rows){const tr=document.createElement('tr');cell(tr,r.id);cell(tr,r.supplier);cell(tr,r.description);cell(tr,new Intl.DateTimeFormat('fr-BE').format(new Date(r.date+'T12:00:00')));cell(tr,euro(gross(r)));const badge=document.createElement('span');badge.className='badge'+(r.status==='Payée'?' paid':'');badge.textContent=r.status;cell(tr,'').append(badge);const btn=document.createElement('button');btn.className='row-open';btn.textContent='Ouvrir';btn.setAttribute('aria-label','Ouvrir '+r.id);btn.addEventListener('click',()=>open(r));cell(tr,'').append(btn);$('rows').append(tr);}
 $('breakdown').replaceChildren();for(const c of categories){const div=document.createElement('div');div.textContent=c;const strong=document.createElement('strong');strong.textContent=euro(rows.filter(r=>r.category===c).reduce((sum,r)=>sum+gross(r),0));div.append(strong);$('breakdown').append(div);}
}
function preview(){const net=Number($('net').value),vat=Number($('vat').value);$('preview').textContent='Total TTC : '+euro(gross({net:Number.isFinite(net)?net:0,vat}));}
function open(r){$('expense-form').reset();$('edit-id').value=r?.id||'';$('dialog-title').textContent=r?'Consulter et modifier l’exemple':'Nouvelle dépense';$('supplier').value=r?.supplier||'';$('description').value=r?.description||'';$('date').value=r?.date||today();$('edit-category').value=r?.category||categories[0];$('edit-status').value=r?.status||statuses[0];$('net').value=r?.net??'';$('vat').value=String(r?.vat??21);preview();$('editor').showModal();}
$('new').addEventListener('click',()=>open());$('new-side').addEventListener('click',()=>open());$('close').addEventListener('click',()=>$('editor').close());
$('filters').addEventListener('submit',e=>e.preventDefault());$('filters').addEventListener('input',render);$('filters').addEventListener('change',render);$('net').addEventListener('input',preview);$('vat').addEventListener('change',preview);
$('expense-form').addEventListener('submit',e=>{e.preventDefault();const id=$('edit-id').value;const r={id:id||'DEMO-'+crypto.randomUUID().slice(0,8).toUpperCase(),supplier:$('supplier').value.trim(),description:$('description').value.trim(),date:$('date').value,category:$('edit-category').value,status:$('edit-status').value,net:Number($('net').value),vat:Number($('vat').value)};if(!valid(r)||!r.supplier||!r.description)return;if(!id&&data.length>=200){notify('La démo est limitée à 200 exemples. Réinitialisez-la pour recommencer.');return;}if(id){data=data.map(old=>old.id===id?r:old);}else data.unshift(r);const saved=persist();$('editor').close();render();if(saved)notify('Exemple enregistré dans ce navigateur.');});
$('reset').addEventListener('click',()=>{if(!confirm('Réinitialiser les exemples ? Cela effacera vos essais dans cette démo.'))return;data=samples();$('filters').reset();const saved=persist();render();if(saved)notify('La démonstration a été réinitialisée.');});
function csvValue(v){let text=String(v);if(/^[\s]*[=+\-@]/.test(text))text="'"+text;return '"'+text.replaceAll('"','""')+'"';}
$('export').addEventListener('click',()=>{const lines=[['Numéro','Fournisseur','Description','Date','Catégorie','Statut','HT','TVA (%)','TTC'],...visible().map(r=>[r.id,r.supplier,r.description,r.date,r.category,r.status,r.net.toFixed(2),r.vat,gross(r).toFixed(2)])];const blob=new Blob(['\uFEFF'+lines.map(line=>line.map(csvValue).join(';')).join('\r\n')],{type:'text/csv;charset=utf-8'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='flowexpense-demo.csv';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);});
render();
