const PARQUE_SERVICIOS = ["Basico","Total Service","Total Service Complemento","Coffee Break"];
const PARQUE_TIPOS = [
  {value:"Inhumación",label:"Inhumacion"},
  {value:"Exhumación",label:"Exhumacion"},
  {value:"Deposito de Cenizas",label:"Deposito de Cenizas"},
  {value:"Resguardo de Cenizas",label:"Resguardo de Cenizas"},
  {value:"Aniversario Luctuoso",label:"Aniversario Luctuoso"},
  {value:"Otro",label:"Otro"}
];
const PARQUE_VELACION = [
  {value:"Capilla Churubusco",label:"Capillas Churubusco"},
  {value:"Capilla Agua Fria",label:"Capillas Agua Fria"},
  {value:"Capilla Externa",label:"Capillas Externas"},
  {value:"sin Velación",label:"Sin Velacion"},
  {value:"Otro",label:"Otro"}
];
const PARQUE_PREVISION = ["Prevision","Uso Inmediato","No Aplica"];
const PARQUE_DESTAPE = ["Primero","Segundo","Tercero","Cuarto","No Aplica"];
const PARQUE_PLACAS = ["Nicho","Urna","Granito","No Aplica"];
const PARQUE_LIQUIDACION = ["Liquidado","No liquidado"];
const PARQUE_PARENTESCO = ["Esposa","Esposo","Familiar","Hermana","Hermano","Hija","Hijo","Madre","Otro","Padre"];
const PARQUE_SECCIONES = ["ORO - RBR","PLN","ORO","SPN","SPV","SAB","PLATINO","PLATA","SJV","SMV"];
const PARQUE_MANZANAS = (()=> {
  const values=[];
  for(let i=0;i<26;i++) values.push(String.fromCharCode(65+i));
  for(let first=0;first<2;first++){
    for(let second=0;second<26;second++){
      values.push(String.fromCharCode(65+first)+String.fromCharCode(65+second));
    }
  }
  ["CX","DX","EX","FX","JP"].forEach(v=>{ if(!values.includes(v)) values.push(v); });
  return values;
})();

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
  items.forEach(item=>{
    const option=document.createElement("option");
    const isObject=item && typeof item==="object";
    option.value=isObject?String(item.value??""):String(item);
    option.textContent=isObject?String(item.label??item.value??""):String(item);
    el.appendChild(option);
  });
}

fillSelect("velacion",PARQUE_VELACION);
fillSelect("previsionUsoInmediato",PARQUE_PREVISION);
fillSelect("tipoServicio",PARQUE_TIPOS);
fillSelect("servicioParque",PARQUE_SERVICIOS);
fillSelect("seccion",PARQUE_SECCIONES);
fillSelect("manzana",PARQUE_MANZANAS);
fillSelect("seccionNueva",PARQUE_SECCIONES);
fillSelect("manzanaNueva",PARQUE_MANZANAS);
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

function formatJdjpDateOnly(raw){
  const digits=String(raw||"").replace(/\D/g,"").slice(0,8);
  if(digits.length<=2) return digits;
  if(digits.length<=4) return digits.slice(0,2)+"/"+digits.slice(2);
  return digits.slice(0,2)+"/"+digits.slice(2,4)+"/"+digits.slice(4);
}

function applyJdjpDateOnlyMask(el){
  if(!el || el.dataset.jdjpMask==="1") return;
  el.dataset.jdjpMask="1";
  el.addEventListener("input",()=>{
    const formatted=formatJdjpDateOnly(el.value);
    if(el.value!==formatted) el.value=formatted;
  });
}

if(window.flatpickr && flatpickr.l10ns?.es)flatpickr.localize(flatpickr.l10ns.es);
document.querySelectorAll(".date-only-picker").forEach(el=>{
  applyJdjpDateOnlyMask(el);
  flatpickr(el,{
    enableTime:false,
    dateFormat:"d/m/Y",
    allowInput:true,
    disableMobile:true,
    locale:"es"
  });
});

function isVipSection(value){
  const sec=String(value||"").trim().toUpperCase();
  return sec.endsWith("V");
}

function syncFraseRule(){
  const destape=document.getElementById("destape")?.value||"";
  const seccion=document.getElementById("seccion")?.value||"";
  const allowed=destape==="Primero" && isVipSection(seccion);
  const wrap=document.getElementById("fraseWrap");
  const input=document.getElementById("frase");
  wrap?.classList.toggle("hidden",!allowed);
  if(input && !allowed) input.value="";
}

const PARQUE_PLACA_POR_TIPO_SERVICIO = {
  "Inhumación":"Granito",
  "Exhumación":"Granito"
};

function syncTipoPlacaPorServicio(){
  const tipo=document.getElementById("tipoServicio")?.value||"";
  const destape=document.getElementById("destape")?.value||"";
  const placa=document.getElementById("tipoPlaca");
  if(!placa)return;

  let forced=PARQUE_PLACA_POR_TIPO_SERVICIO[tipo]||"";
  if(tipo==="Deposito de Cenizas"){
    forced=destape==="Primero"?"Nicho":"No Aplica";
  }

  const wasAuto=placa.dataset.autoAssigned==="1";

  if(forced){
    placa.value=forced;
    placa.disabled=true;
    placa.dataset.autoAssigned="1";
    placa.title="Autoasignado por Tipo de Servicio y Destape";
  }else{
    placa.disabled=false;
    placa.title="";
    if(wasAuto){
      placa.value="";
    }
    placa.dataset.autoAssigned="0";
  }

  syncPropertyRules();
}

function syncPropertyRules(){
  const destape=document.getElementById("destape")?.value||"";
  const placa=document.getElementById("tipoPlaca")?.value||"";
  const nicho=document.getElementById("nichoFields");
  const warning=document.getElementById("placaWarning");
  const familia=document.getElementById("nombreFamilia");
  const invalid=placa==="Nicho" && destape!=="" && destape!=="Primero";
  nicho?.classList.toggle("hidden",placa!=="Nicho");
  warning?.classList.toggle("hidden",!invalid);
  if(familia){
    familia.required=placa==="Nicho";
    if(placa!=="Nicho")familia.value="";
  }
  if(invalid){
    document.getElementById("tipoPlaca").setCustomValidity("Nicho solo aplica cuando Destape = Primero.");
  }else{
    document.getElementById("tipoPlaca").setCustomValidity("");
  }
  syncFraseRule();
  syncLocation();
  syncNewLocation();
}
document.getElementById("destape")?.addEventListener("input",()=>{syncTipoPlacaPorServicio();syncPropertyRules();});
["tipoPlaca","seccion","manzana","numLoteNicho"].forEach(id=>document.getElementById(id)?.addEventListener("input",syncPropertyRules));
document.getElementById("tipoServicio")?.addEventListener("change",syncTipoPlacaPorServicio);
document.getElementById("servicioParque")?.addEventListener("change",()=>{
  syncLocation();
  syncNewLocation();
});

function syncNewLocation(){
  const sec=document.getElementById("seccionNueva")?.value||"";
  const man=document.getElementById("manzanaNueva")?.value||"";
  const lote=document.getElementById("numLoteNichoNuevo")?.value||"";
  const parts=[];
  const prefix=servicePrefix();
  if(prefix)parts.push(prefix);
  if(sec)parts.push(sec);
  if(lote)parts.push(lote);
  if(man)parts.push(man);

  const value=parts.join(" - ");
  const preview=document.getElementById("ubicacionNuevaPreview");
  const hidden=document.getElementById("ubicacionNueva");
  if(preview)preview.textContent=value||"—";
  if(hidden)hidden.value=value;
}

function syncReubicacion(){
  const active=document.getElementById("requiereReubicacion")?.checked===true;
  document.getElementById("reubicacionFields")?.classList.toggle("hidden",!active);

  const seccionNueva=document.getElementById("seccionNueva");
  const manzanaNueva=document.getElementById("manzanaNueva");
  const loteNuevo=document.getElementById("numLoteNichoNuevo");
  const motivo=document.getElementById("motivoReubicacion");

  if(seccionNueva)seccionNueva.required=active;
  if(manzanaNueva)manzanaNueva.required=active;
  if(loteNuevo)loteNuevo.required=active;
  if(motivo)motivo.required=active;

  if(!active){
    if(seccionNueva)seccionNueva.value="";
    if(manzanaNueva)manzanaNueva.value="";
    if(loteNuevo)loteNuevo.value="";
    const hidden=document.getElementById("ubicacionNueva");
    if(hidden)hidden.value="";
  }

  syncNewLocation();
}
document.getElementById("requiereReubicacion")?.addEventListener("change",syncReubicacion);
["seccionNueva","manzanaNueva","numLoteNichoNuevo"].forEach(id=>{
  document.getElementById(id)?.addEventListener("input",syncNewLocation);
  document.getElementById(id)?.addEventListener("change",syncNewLocation);
});


function servicePrefix(){
  const service=document.getElementById("servicioParque")?.value||"";
  if(service==="Total Service Complemento")return "TSC";
  if(service==="Total Service")return "TS";
  return "";
}

function syncLocation(){
  const sec=document.getElementById("seccion")?.value||"";
  const man=document.getElementById("manzana")?.value||"";
  const lote=document.getElementById("numLoteNicho")?.value||"";
  const parts=[];
  const prefix=servicePrefix();
  if(prefix)parts.push(prefix);
  if(sec)parts.push(sec);
  if(lote)parts.push(lote);
  if(man)parts.push(man);
  const value=parts.join(" - ")||"—";
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
  const fechaInicio=fieldValue("fechaInicio");
  const horaInicio=fieldValue("horaInicio");
  const fechaFin=fieldValue("fechaFin");
  const horaFin=fieldValue("horaFin");
  return {
    _area:"parque",
    area:"parque",
    ubicacion:document.getElementById("ubicacionPreview")?.textContent==="—"?"":(document.getElementById("ubicacionPreview")?.textContent||""),
    fechaInicio,
    horaInicio,
    fechaFin,
    horaFin,
    fechaHoraInicio:[fechaInicio,horaInicio].filter(Boolean).join(" "),
    fechaHoraFin:[fechaFin,horaFin].filter(Boolean).join(" "),
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
    frase:(fieldValue("destape")==="Primero" && isVipSection(fieldValue("seccion")))?fieldValue("frase"):"",
    fechaNacimiento:fieldValue("fechaNacimiento"),
    fechaDefuncion:fieldValue("fechaDefuncion"),
    estatusLiquidacion:fieldValue("estatusLiquidacion"),
    requiereReubicacion:fieldValue("requiereReubicacion"),
    seccionNueva:fieldValue("seccionNueva"),
    manzanaNueva:fieldValue("manzanaNueva"),
    numLoteNichoNuevo:fieldValue("numLoteNichoNuevo"),
    ubicacionNueva:fieldValue("ubicacionNueva"),
    motivoReubicacion:fieldValue("motivoReubicacion"),
    observaciones:fieldValue("observaciones")
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
    const draftId=document.getElementById("draftId")?.value||"";
    if(draftId)body.append("draftId",draftId);
    const editItemId=document.getElementById("editItemId")?.value||"";
    if(editItemId)body.append("editItemId",editItemId);
    const response=await fetch("api/registro-parque.php",{
      method:"POST",
      body,
      credentials:"same-origin",
      headers:{"Accept":"application/json"}
    });
    const result=await response.json().catch(()=>null);
    if(!response.ok||!result?.ok)throw new Error(result?.message||("HTTP "+response.status));

    const lines=[
      result.modified===true?"Servicio Parque modificado correctamente.":"Servicio Parque registrado correctamente.",
      "ID: "+result.itemId,
      "Lista: Eventos Parque"
    ];

    if(result.backgroundProcessing===true){
      lines.push("Automatizaciones: PROCESANDO EN SEGUNDO PLANO");
    }else{
      lines.push(
        result.calendar?.created
          ? "Calendario: CREADO DIRECTAMENTE"
          : (result.calendar?.skipped ? "Calendario: "+(result.calendar?.reason||"OMITIDO") : ("Calendario: "+(result.calendar?.error||"NO CREADO")))
      );
      lines.push(
        result.email?.sent ? "Correo operativo: ENVIADO" : ("Correo operativo: "+(result.email?.error||"NO ENVIADO"))
      );
    }

    if(result.backgroundProcessing!==true && Array.isArray(result.plateEmails)){
      result.plateEmails.forEach(plate=>{
        const label=String(plate?.kind||"placa").toUpperCase();
        lines.push(
          plate?.sent
            ? "Correo Placa "+label+": ENVIADO"
            : "Correo Placa "+label+": "+(plate?.error||"NO ENVIADO")
        );
      });
    }

    if(status)status.textContent=lines.join(" | ");
    alert(lines.join("\n"));
    if(button)button.textContent="Registrado";
  }catch(error){
    if(status)status.textContent="No fue posible registrar: "+(error?.message||error);
    if(button){button.disabled=false;button.textContent=original;}
  }
});


function parqueComparable(value){
  return String(value??"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").trim().toLowerCase();
}
function setParqueField(name,value){
  const el=form.elements.namedItem(name);
  if(!el)return;
  if(el instanceof RadioNodeList){el.value=value??"";return;}
  if(el.type==="checkbox"){
    el.checked=!!value;
    el.dispatchEvent(new Event("change",{bubbles:true}));
    return;
  }
  if(el instanceof HTMLSelectElement && !el.multiple){
    const wanted=parqueComparable(value);
    const match=Array.from(el.options).find(o=>parqueComparable(o.value)===wanted || parqueComparable(o.textContent)===wanted);
    el.value=match?match.value:(value??"");
  }else{
    el.value=value??"";
  }
  el.dispatchEvent(new Event("change",{bubbles:true}));
  el.dispatchEvent(new Event("input",{bubbles:true}));
}

function applyParqueDraft(p){
  if(!p||typeof p!=="object")return;
  [
    "fechaInicio","horaInicio","fechaFin","horaFin","velacion","previsionUsoInmediato",
    "tipoServicio","servicio","asistenteFunerarioTexto","numeroContrato","seccion",
    "manzana","numLoteNicho","destape","tipoPlaca","nombreFamilia","titular",
    "fallecido","parentescoTitular","frase","fechaNacimiento","fechaDefuncion",
    "estatusLiquidacion","seccionNueva","manzanaNueva","numLoteNichoNuevo","ubicacionNueva","motivoReubicacion","observaciones"
  ].forEach(k=>setParqueField(k,p[k]??""));
  setParqueField("requiereCambioUrna",!!p.requiereCambioUrna);
  setParqueField("requiereReubicacion",!!p.requiereReubicacion);
  syncTipoPlacaPorServicio();
  syncPropertyRules();
  syncReubicacion();
  syncSummary();
}

async function saveParqueDraft(){
  const button=document.getElementById("saveDraftBtn");
  const status=document.getElementById("status");
  if(!button)return;
  const original=button.textContent;
  button.disabled=true;
  button.textContent="Guardando...";
  try{
    const body=new FormData();
    body.append("payload",JSON.stringify(payload()));
    const current=document.getElementById("draftId")?.value||"";
    if(current)body.append("draftId",current);
    const response=await fetch("api/guardar-borrador.php",{method:"POST",body,credentials:"same-origin",headers:{"Accept":"application/json"}});
    const result=await response.json().catch(()=>null);
    if(!response.ok||!result?.ok)throw new Error(result?.message||("HTTP "+response.status));
    document.getElementById("draftId").value=result.draftId||"";
    const url=new URL(location.href);
    url.searchParams.set("draft",result.draftId);
    url.searchParams.delete("nuevo");
    history.replaceState(null,"",url);
    if(status)status.textContent="Borrador guardado. Puedes continuar después desde Mis servicios.";
    button.textContent="Borrador guardado";
    setTimeout(()=>{button.textContent=original;button.disabled=false;},1500);
  }catch(error){
    if(status)status.textContent="No fue posible guardar el borrador: "+(error?.message||error);
    button.textContent=original;
    button.disabled=false;
  }
}

async function loadParquePublishedEdit(){
  const id=new URLSearchParams(location.search).get("edit");
  if(!id)return false;

  const status=document.getElementById("status");
  try{
    if(status)status.textContent="Cargando servicio publicado...";
    const response=await fetch("api/publicado-api.php?area=parque&id="+encodeURIComponent(id),{
      cache:"no-store",
      credentials:"same-origin",
      headers:{"Accept":"application/json"}
    });
    const result=await response.json().catch(()=>null);
    if(!response.ok||!result?.ok)throw new Error(result?.message||("HTTP "+response.status));

    document.getElementById("editItemId").value=String(result.itemId||id);
    document.getElementById("draftId").value="";
    applyParqueDraft(result.payload||{});

    const bannerTitle=document.querySelector(".form-banner h1");
    if(bannerTitle)bannerTitle.textContent="Modificar servicio";
    const bannerCopy=document.querySelector(".form-banner p");
    if(bannerCopy)bannerCopy.textContent="Actualiza la información del registro publicado. Al guardar, se modificará el mismo elemento de SharePoint.";
    const submit=document.getElementById("submitBtn");
    if(submit)submit.textContent="Guardar modificación";
    const draftButton=document.getElementById("saveDraftBtn");
    if(draftButton)draftButton.hidden=true;
    if(status)status.textContent="Servicio publicado cargado. Los cambios actualizarán el registro existente y reenviarán el correo como MODIFICADO.";
    return true;
  }catch(error){
    if(status)status.textContent="No fue posible cargar el servicio publicado: "+(error?.message||error);
    return true;
  }
}

async function loadParqueDraft(){
  const status=document.getElementById("status");
  const boot=window.JDJP_PARQUE_INITIAL_DRAFT||null;

  if(boot && boot.id){
    document.getElementById("draftId").value=boot.id;
    if(boot.payload && typeof boot.payload==="object"){
      applyParqueDraft(boot.payload);
      if(status)status.textContent="Borrador cargado. Continúa la captura o publícalo cuando esté completo.";
      return;
    }
    if(boot.error && status){
      status.textContent="No fue posible cargar el borrador: "+boot.error;
    }
  }

  const id=new URLSearchParams(location.search).get("draft");
  if(!id)return;

  try{
    if(status)status.textContent="Cargando borrador...";
    const response=await fetch("api/guardar-borrador.php?id="+encodeURIComponent(id),{cache:"no-store",credentials:"same-origin"});
    const result=await response.json().catch(()=>null);
    if(!response.ok||!result?.ok)throw new Error(result?.message||("HTTP "+response.status));
    document.getElementById("draftId").value=id;
    applyParqueDraft(result.draft?.payload||{});
    if(status)status.textContent="Borrador cargado. Continúa la captura o publícalo cuando esté completo.";
  }catch(error){
    if(status)status.textContent="No fue posible cargar el borrador: "+(error?.message||error);
  }
}

function resetParqueForm(){
  if(!confirm("¿Deseas limpiar toda la captura?"))return;
  form.reset();
  document.getElementById("draftId").value="";
  document.getElementById("editItemId").value="";
  const url=new URL(location.href);
  url.searchParams.delete("draft");
  url.searchParams.delete("edit");
  url.searchParams.set("nuevo","1");
  history.replaceState(null,"",url);
  syncTipoPlacaPorServicio();
  syncPropertyRules();
  syncReubicacion();
  showStep(0);
  const status=document.getElementById("status");
  if(status)status.textContent="Listo para registrar en SharePoint.";
}

document.getElementById("saveDraftBtn")?.addEventListener("click",saveParqueDraft);
document.getElementById("resetBtn")?.addEventListener("click",resetParqueForm);

syncTipoPlacaPorServicio();
syncPropertyRules();
syncReubicacion();
showStep(0);
(async()=>{
  const editing=await loadParquePublishedEdit();
  if(!editing)await loadParqueDraft();
})();
