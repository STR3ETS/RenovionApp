// Bouwt de fabrieksstaat van Renovion Groep: de volledige structuur van het prototype, zonder demodata.
const fs=require('fs');
const S=JSON.parse(fs.readFileSync(process.argv[2]));
const F=JSON.parse(JSON.stringify(S));
const EMPTY_ARR=['emps','clients','projects','invoices','blocks','tasks','agenda','outbox','notifs','subs','teams','werkbonnen','rfqs','inkoop','declaraties','klantBerichten','klantNotities','klantVerzoeken','rapporten','autoQueue','autoLog','lib','pos','extraUsers','offVragen','service','offHist','mailIn'];
for(const k of EMPTY_ARR) F[k]=[];
for(const k of ['kwal','klus','contacts','leadNext','risks','autoFired','certMail']) F[k]={};
F.seq=Object.fromEntries(Object.keys(S.seq).map(k=>[k,0]));
F.prefs={novaMode:S.prefs.novaMode||'bevestigen'};
F.chat={channels:S.chat.channels.filter(c=>['algemeen','afdeling'].includes(c.type)),msgs:[],read:{}};
F.ai={cfg:S.ai.cfg,runs:[],approvals:[],audit:[],idem:{},incidents:[],tickets:[],cases:[],seq:Object.fromEntries(Object.keys(S.ai.seq).map(k=>[k,0])),flow:{},seeded:S.ai.seeded};
F.fin={bank:0,bedrijfskosten:0,loonIntern:0,loonKantoor:0,hist:S.fin.hist.map(()=>0),doelMaand:S.fin.doelMaand};
F.mkt={maanden:S.mkt.maanden,kanalen:S.mkt.kanalen.map(c=>({k:c.k,leads:c.leads.map(()=>0),kosten:c.kosten.map(()=>0),gewonnen:c.gewonnen.map(()=>0),omzet:c.omzet.map(()=>0)})),kw:[],lokaal:[],pages:[],tech:[],forms:[]};
for(const a of F.automations){ a.teller=0; a.laatste=null; }
for(const p of F.prijslijst){ p.hist=[]; }
F.academy={done:{},started:{},hideWelcome:false,last:null};
F.autoInit=true;
fs.writeFileSync(process.argv[3],JSON.stringify(F,null,1));
const left=Object.entries(F).filter(([k,v])=>Array.isArray(v)&&v.length).map(([k,v])=>k+':'+v.length);
console.log('behouden lijsten:',left.join(' '));
console.log('chat kanalen:',F.chat.channels.map(c=>c.id).join(','));
console.log('bytes',fs.statSync(process.argv[3]).size);
