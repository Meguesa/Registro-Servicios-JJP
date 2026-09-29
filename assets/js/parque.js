const PARQUE_SERVICIOS = ["Basico","Total Service","Total Service Complemento","Coffee Break"];
const PARQUE_TIPOS = ["Inhumación","Deposito de Cenizas","Resguardo de Cenizas","Aniversario Luctuoso"];
const PARQUE_VELACION = ["Capilla Churubusco","Capilla Externa","Capilla Agua Fria","Otro","sin Velación"];
const PARQUE_PREVISION = ["Prevision","Uso Inmediato","No Aplica"];
const PARQUE_DESTAPE = ["Primero","Segundo","Tercero","Cuarto","No Aplica"];
const PARQUE_PLACAS = ["Nicho","Urna","Granito"];
const PARQUE_LIQUIDACION = ["Liquidado","No liquidado"];
const PARQUE_PARENTESCO = ["Padre","Madre","Hijo","Hermano"];
const PARQUE_SECCIONES = ["ORO - RBR","PLN","ORO","SPN","SPV","SAB","PLATINO","PLATA","SJV","SMV"];
const PARQUE_MANZANAS = ["A","B","C","D","E","F","G","K","L","M","R","U","AX","AF","BX","CX","DX"];

function fillSelect(id, items, placeholder="Seleccionar"){
  const el=document.getElementById(id);
  if(!el)return;
  el.innerHTML="";
  const ph=document.createElement("option");
  ph.value="";
  ph.textContent=placeholder;
  ph.disabled=true;
  ph.hidden=true;
  ph.selected=true;
  el.appendChild(ph);
  items.forEach(value=>{
    const option=document.createElement("option");
    option.value=value;
    option.textContent=value;
    el.appendChild(option);
  });
}

fillSelect("velacion",PARQUE_VELACION);
fillSelect("previsionUsoInmediato",PARQUE_PREVISION);
fillSelect("tipoServicio",PARQUE_TIPOS);
fillSelect("servicioParque",PARQUE_SERVICIOS);
fillSelect("seccion",PARQUE_SECCIONES);
fillSelect("manzana",PARQUE_MANZANAS);
fillSelect("destape",PARQUE_DESTAPE);
fillSelect("tipoPlaca",PARQUE_PLACAS);
fillSelect("parentescoTitular",PARQUE_PARENTESCO);
fillSelect("estatusLiquidacion",PARQUE_LIQUIDACION);

const form=document.getElementById("parqueForm");
let currentStep=0;

function panels(){return Array.from(document.querySelectorAll(".wizard-panel"));}
function showStep(step){
  const p=panels();
  currentStep=Math.max(0,Math.min(step,p.length-1));
  p.forEach((panel,index)=>panel.classList.toggle("active",index===currentStep));
  document.querySelectorAll(".wizard-step").forEach((button,index)=>{
    button.classList.toggle("active",index===currentStep);
    button.classList.toggle("completed",index<currentStep);
  });
  const number=document.getElementById("currentStepNumber");
  if(number)number.textContent=String(currentStep+1);
  const prev=document.getElementById("prevStep");
  const next=document.getElementById("nextStep");
  const submit=document.getElementById("submitBtn");
  if(prev)prev.style.visibility=currentStep===0?"hidden":"visible";
  if(next)next.classList.toggle("hidden",currentStep===p.length-1);
  if(submit)submit.classList.toggle("hidden",currentStep!==p.length-1);
  if(currentStep===p.length-1)syncSummary();
  window.scrollTo({top:0,behavior:"smooth"});
}

function validateStep(){
  const panel=panels()[currentStep];
  if(!panel)return true;
  for(const el of Array.from(panel.querySelectorAll("[required]"))){
    if(el.closest(".hidden"))continue;
    if(!el.checkValidity()){el.reportValidity();return false;}
  }
  return true;
}

document.getElementById("nextStep")?.addEventListener("click",()=>{if(validateStep())showStep(currentStep+1);});
document.getElementById("prevStep")?.addEventListener("click",()=>showStep(currentStep-1));
document.querySelectorAll(".wizard-step").forEach(button=>{
  button.addEventListener("click",()=>{
    const target=Number(button.dataset.stepTarget||0);
    if(target<=currentStep||validateStep())showStep(target);
  });
});

if(window.flatpickr && flatpickr.l10ns?.es)flatpickr.localize(flatpickr.l10ns.es);
document.querySelectorAll(".datetime-picker").forEach(el=>{
  flatpickr(el,{enableTime:true,time_24hr:true,dateFormat:"d/m/Y H:i",allowInput:true,disableMobile:true,locale:"es"});
});
document.querySelectorAll(".date-only-picker").forEach(el=>{
  flatpickr(el,{enableTime:false,dateFormat:"d/m/Y",allowInput:true,disableMobile:true,locale:"es"});
});

function syncPropertyRules(){
  const destape=document.getElementById("destape")?.value||"";
  const placa=document.getElementById("tipoPlaca")?.value||"";
  const nicho=document.getElementById("nichoFields");
  const warning=document.getElementById("placaWarning");
  const familia=document.getElementById("nombreFamilia");
  const invalid=placa==="Nicho" && destape!=="" && destape!=="Primero";
  nicho?.classList.toggle("hidden",placa!=="Nicho");
  warning?.classList.toggle("hidden",!invalid);
  if(familia)familia.required=placa==="Nicho";
  if(invalid){
    document.getElementById("tipoPlaca").setCustomValidity("Nicho solo aplica cuando Destape = Primero.");
  }else{
    document.getElementById("tipoPlaca").setCustomValidity("");
  }
  syncLocation();
}
["destape","tipoPlaca","seccion","manzana","numLoteNicho"].forEach(id=>document.getElementById(id)?.addEventListener("input",syncPropertyRules));

function syncReubicacion(){
  const active=document.getElementById("requiereReubicacion")?.checked===true;
  document.getElementById("reubicacionFields")?.classList.toggle("hidden",!active);
  const nueva=document.getElementById("ubicacionNueva");
  const motivo=document.getElementById("motivoReubicacion");
  if(nueva)nueva.required=active;
  if(motivo)motivo.required=active;
}
document.getElementById("requiereReubicacion")?.addEventListener("change",syncReubicacion);

function syncTestModeUi(){
  const active=document.getElementById("modoPrueba")?.checked===true;
  const pill=document.getElementById("modePill");
  const help=document.getElementById("testModeHelp");
  if(pill)pill.textContent=active?"Prueba · conectado a SharePoint":"Producción · conectado a SharePoint";
  if(help)help.hidden=!active;
}
document.getElementById("modoPrueba")?.addEventListener("change",syncTestModeUi);

function syncLocation(){
  const sec=document.getElementById("seccion")?.value||"";
  const man=document.getElementById("manzana")?.value||"";
  const lote=document.getElementById("numLoteNicho")?.value||"";
  const parts=[];
  if(sec)parts.push(sec);
  if(man)parts.push("MZ "+man);
  if(lote)parts.push("LOTE/NICHO "+lote);
  const value=parts.join(" · ")||"—";
  const target=document.getElementById("ubicacionPreview");
  if(target)target.textContent=value;
}

function fieldValue(name){
  const el=form.elements.namedItem(name);
  if(!el)return "";
  if(el instanceof RadioNodeList)return String(el.value||"").trim();
  if(el.type==="checkbox")return el.checked===true;
  return String(el.value||"").trim();
}

function payload(){
  return {
    fechaHoraInicio:fieldValue("fechaHoraInicio"),
    fechaHoraFin:fieldValue("fechaHoraFin"),
    velacion:fieldValue("velacion"),
    previsionUsoInmediato:fieldValue("previsionUsoInmediato"),
    tipoServicio:fieldValue("tipoServicio"),
    servicio:fieldValue("servicio"),
    asistenteFunerarioTexto:fieldValue("asistenteFunerarioTexto"),
    numeroContrato:fieldValue("numeroContrato"),
    seccion:fieldValue("seccion"),
    manzana:fieldValue("manzana"),
    numLoteNicho:fieldValue("numLoteNicho"),
    destape:fieldValue("destape"),
    tipoPlaca:fieldValue("tipoPlaca"),
    nombreFamilia:fieldValue("nombreFamilia"),
    requiereCambioUrna:fieldValue("requiereCambioUrna"),
    titular:fieldValue("titular"),
    fallecido:fieldValue("fallecido"),
    parentescoTitular:fieldValue("parentescoTitular"),
    frase:fieldValue("frase"),
    fechaNacimiento:fieldValue("fechaNacimiento"),
    fechaDefuncion:fieldValue("fechaDefuncion"),
    estatusLiquidacion:fieldValue("estatusLiquidacion"),
    requiereReubicacion:fieldValue("requiereReubicacion"),
    ubicacionNueva:fieldValue("ubicacionNueva"),
    motivoReubicacion:fieldValue("motivoReubicacion"),
    observaciones:fieldValue("observaciones"),
    modoPrueba:document.getElementById("modoPrueba")?.checked===true
  };
}

function syncSummary(){
  const p=payload();
  const set=(id,value)=>{const el=document.getElementById(id);if(el)el.textContent=value||"—";};
  set("summaryFallecido",p.fallecido);
  set("summaryTipoServicio",p.tipoServicio+" / "+p.servicio);
  set("summaryUbicacion",document.getElementById("ubicacionPreview")?.textContent||"—");
  set("summaryPlaca",p.tipoPlaca+(p.tipoPlaca==="Nicho"&&p.nombreFamilia?" · "+p.nombreFamilia:""));
  set("summaryLiquidacion",p.estatusLiquidacion);
  set("summaryModo",p.modoPrueba?"PRUEBA":"PRODUCCIÓN");
}

form.addEventListener("submit",async e=>{
  e.preventDefault();
  if(!validateStep())return;

  const button=document.getElementById("submitBtn");
  const status=document.getElementById("status");
  const original=button?.textContent||"Registrar Servicio Parque";
  if(button){button.disabled=true;button.textContent="Registrando...";}
  if(status)status.textContent="Creando servicio en Eventos Parque...";

  try{
    const body=new FormData();
    body.append("payload",JSON.stringify(payload()));
    const response=await fetch("api/registro-parque.php",{
      method:"POST",
      body,
      credentials:"same-origin",
      headers:{"Accept":"application/json"}
    });
    const result=await response.json().catch(()=>null);
    if(!response.ok||!result?.ok)throw new Error(result?.message||("HTTP "+response.status));

    const lines=[
      "Servicio Parque registrado correctamente.",
      "ID: "+result.itemId,
      "Lista: Eventos Parque",
      result.modoPrueba?"Modo: PRUEBA":"Modo: PRODUCCIÓN",
      result.calendar?.created ? "Calendario: CREADO DIRECTAMENTE" : ("Calendario: "+(result.calendar?.error||"NO CREADO"))
    ];
    if(status)status.textContent=lines.join(" | ");
    alert(lines.join("\n"));
    if(button)button.textContent="Registrado";
  }catch(error){
    if(status)status.textContent="No fue posible registrar: "+(error?.message||error);
    if(button){button.disabled=false;button.textContent=original;}
  }
});

syncPropertyRules();
syncReubicacion();
syncTestModeUi();
showStep(0);
