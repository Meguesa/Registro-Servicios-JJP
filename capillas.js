const SERVICIOS=["Cremación","Cremación Directa (con velación)","Cremación Directa (sin velación)","Inhumación","Inhumación Directa","RA - Inhumacion","Renta de Capillas","Velación a Domicilio","Deposito Cenizas","Resguardo Urna","Traslado","Aniversario Luctuoso"];
const UBICACIONES=["Apodaca","Churubusco","Crematorio","Parque JdJP"];
const SALAS=["Sala 1","Sala 2","Sala 3"];
const TIEMPOS=["4H","6H","12H","24H"];
const PREVISION=["Previsión","Uso Inmediato"];
const ATAUDES=["Ataud Madera Basico","Ataud Madera de Lujo","Ataud Madera Exclusivo","Ataud Metalico Basico","Ataud Metalico Exclusivo","Ataud Metalico Intermedio","Ataud Metalico Jumbo","Urna Basica","Urna Madera","Urna Madera RA","Urna Marmol"];
const EMBALSAMADORES=["Bibi","No Aplica","Otro"];
const EXTRAS=["Misa y Coro","Flores","Cobro por Enfermedad","Horas Extras","Cambio de Ataud","Cambio a Cremacion","Cambio de Capilla","Cambio de Urna","Traslado","Resguardo","Retiro de Marcapasos","Sobrepeso","Destape","Impuestos","No Aplica"];
const CODIGO_SERVICIO={"Inhumación":"VI","Cremación":"VC","Cremación Directa (con velación)":"CD","Cremación Directa (sin velación)":"CD","Inhumación Directa":"ID","Renta de Capillas":"RENTCAP","Velación a Domicilio":"VD"};
const CODIGO_ATAUD={"Ataud Madera Basico":"ATMADBA","Ataud Madera de Lujo":"ATMADLX","Ataud Madera Exclusivo":"ATMADEX","Ataud Metalico Basico":"ATMETBA","Ataud Metalico Exclusivo":"ATMETEX","Ataud Metalico Intermedio":"ATMETEX","Ataud Metalico Jumbo":"ATMETEX","Urna Basica":"URNABAS","Urna Marmol":"URNABAS","Urna Madera":"URNABAS","Urna Madera RA":"URNABAS"};

function fill(id,items,placeholder="Seleccionar"){
  const el=document.getElementById(id);
  if(!el)return;
  if(!el.multiple) el.innerHTML='<option value="">'+placeholder+'</option>';
  items.forEach(v=>{
    const o=document.createElement('option');
    o.value=v;
    o.textContent=v;
    el.appendChild(o);
  });
}
fill("servicio",SERVICIOS);
fill("ubicacion",UBICACIONES);
fill("sala",SALAS);
fill("tiempoCapillas",TIEMPOS);
fill("prevision",PREVISION);
fill("tipoAtaud",ATAUDES);
fill("embalsamador",EMBALSAMADORES);
fill("serviciosExtra",EXTRAS,"");

const form=document.getElementById("capillasForm");
const servicio=document.getElementById("servicio");
const exequia=document.getElementById("llevaExequia");
const tipoAtaud=document.getElementById("tipoAtaud");
const numeroServicio=document.getElementById("numeroServicio");
const extrasSelect=document.getElementById("serviciosExtra");
const precioVenta=document.getElementById("precioVenta");
const extrasMontos=document.getElementById("extrasMontos");

EXTRAS.filter(x=>x!=="No Aplica").forEach(x=>{
  const wrap=document.createElement("label");
  wrap.className="extra-item";
  wrap.dataset.extra=x;
  wrap.textContent=x;
  const input=document.createElement("input");
  input.type="number";
  input.min="0";
  input.step="0.01";
  input.value="0";
  input.dataset.extraInput=x;
  wrap.appendChild(input);
  extrasMontos.appendChild(wrap);
});

function selectedExtras(){
  return Array.from(extrasSelect.selectedOptions).map(o=>o.value);
}
function setRequired(id,on){
  const el=document.getElementById(id);
  if(el) el.required=!!on;
}
function toggle(id,on){
  const el=document.getElementById(id);
  if(el) el.classList.toggle("hidden",!on);
}
function calcAge(){
  const n=document.getElementById("fechaNacimiento").value;
  const d=document.getElementById("fechaDefuncion").value;
  const out=document.getElementById("edad");
  if(!n||!d){out.value="";return;}
  const birth=new Date(n+"T00:00:00");
  const death=new Date(d);
  let age=death.getFullYear()-birth.getFullYear();
  const birthday=new Date(death.getFullYear(),birth.getMonth(),birth.getDate());
  if(birthday>death) age--;
  out.value=Math.max(0,age);
}
function updateRules(){
  const svc=servicio.value;
  const sinVelacion=svc==="Cremación Directa (sin velación)";
  const renta=svc==="Renta de Capillas";
  const crem=["Cremación","Cremación Directa (con velación)","Cremación Directa (sin velación)"].includes(svc);
  const tieneExequia=exequia.checked&&!sinVelacion;

  ["wrapSala","wrapInicio","wrapTermino","wrapExequiaToggle","wrapTiempo"].forEach(id=>toggle(id,!sinVelacion));
  toggle("wrapExequia",tieneExequia);
  setRequired("sala",!sinVelacion);
  setRequired("inicio",!sinVelacion);
  setRequired("termino",!sinVelacion);
  setRequired("tiempoCapillas",!sinVelacion);
  setRequired("horaExequia",tieneExequia);

  toggle("wrapTipoAtaud",!renta);
  setRequired("tipoAtaud",!renta);

  toggle("crematorioSection",crem);
  setRequired("referenciaCrematorio",crem);
  setRequired("inicioCrematorio",crem);
  setRequired("personalCrematorio",crem);

  toggle("inhumacionSection",svc==="Inhumación");

  const cod=CODIGO_SERVICIO[svc]||"";
  const ata=renta?"":(CODIGO_ATAUD[tipoAtaud.value]||"");
  document.getElementById("codigoServicio").textContent=cod||"—";
  document.getElementById("codigoAtaud").textContent=ata||"—";
  const num=numeroServicio.value.trim();
  let ref="—";
  if(cod&&num){
    ref=renta ? (cod+" - "+num) : (ata ? (cod+" - "+ata+" - "+num) : (cod+" - "+num));
  }
  document.getElementById("referenciaPreview").textContent=ref;
}
function updateExtras(){
  const selected=selectedExtras();
  document.querySelectorAll(".extra-item").forEach(el=>{
    el.classList.toggle("active",selected.includes(el.dataset.extra));
  });
  updateTotal();
}
function updateTotal(){
  let total=parseFloat(precioVenta.value||"0")||0;
  selectedExtras().forEach(name=>{
    const input=document.querySelector('[data-extra-input="'+name.replace(/"/g,'\\"')+'"]');
    if(input) total+=parseFloat(input.value||"0")||0;
  });
  document.getElementById("ventaTotal").textContent=total.toLocaleString("en-US",{style:"currency",currency:"USD"});
}

servicio.addEventListener("change",updateRules);
exequia.addEventListener("change",updateRules);
tipoAtaud.addEventListener("change",updateRules);
numeroServicio.addEventListener("input",updateRules);
document.getElementById("fechaNacimiento").addEventListener("change",calcAge);
document.getElementById("fechaDefuncion").addEventListener("change",calcAge);
extrasSelect.addEventListener("change",updateExtras);
precioVenta.addEventListener("input",updateTotal);
extrasMontos.addEventListener("input",updateTotal);
document.getElementById("resetBtn").addEventListener("click",()=>{
  form.reset();
  document.querySelectorAll(".extra-item input").forEach(i=>i.value="0");
  calcAge();
  updateRules();
  updateExtras();
});
form.addEventListener("submit",e=>e.preventDefault());

updateRules();
updateExtras();
