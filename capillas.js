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

  if(!el.multiple){
    el.innerHTML="";
    const ph=document.createElement("option");
    ph.value="";
    ph.textContent=placeholder;
    ph.disabled=true;
    ph.hidden=true;
    ph.selected=true;
    el.appendChild(ph);
  }

  items.forEach(v=>{
    const o=document.createElement("option");
    o.value=v;
    o.textContent=v;
    el.appendChild(o);
  });
}
fill("servicio",SERVICIOS,"Ej. Inhumación, Cremación");
fill("ubicacion",UBICACIONES,"Ej. Churubusco, Apodaca");
fill("sala",SALAS,"Ej. Sala 1");
fill("tiempoCapillas",TIEMPOS,"Ej. 12H");
fill("prevision",PREVISION,"Ej. Uso Inmediato");
fill("tipoAtaud",ATAUDES,"Ej. Ataud Madera Basico");
fill("embalsamador",EMBALSAMADORES,"Ej. Bibi");
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
  input.placeholder="Ej. 1500.00";
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
  const parseDate=(value,withTime)=>{
    if(value.includes("/")){
      const parts=value.trim().split(" ");
      const dp=parts[0].split("/");
      if(dp.length!==3)return null;
      let hh=0,mm=0;
      if(withTime&&parts[1]){
        const tp=parts[1].split(":");
        hh=Number(tp[0]||0);mm=Number(tp[1]||0);
      }
      const result=new Date(Number(dp[2]),Number(dp[1])-1,Number(dp[0]),hh,mm,0,0);
      return Number.isNaN(result.getTime())?null:result;
    }
    const result=new Date(withTime?value:value+"T00:00:00");
    return Number.isNaN(result.getTime())?null:result;
  };
  const birth=parseDate(n,false);
  const death=parseDate(d,true);
  if(!birth||!death){out.value="";return;}
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
  const requierePlaca=document.getElementById("requierePlaca");
  if(crem && requierePlaca) requierePlaca.checked=true;
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
  document.querySelectorAll(".extra-item input").forEach(i=>i.value="");
  calcAge();
  updateRules();
  updateExtras();
});
form.addEventListener("submit",e=>e.preventDefault());

updateRules();
updateExtras();


/* Navegación visual tipo Solicitud de Venta. No modifica la lógica de negocio. */
let currentWizardStep=0;
function wizardPanels(){return Array.from(document.querySelectorAll(".wizard-panel"));}
function showWizardStep(step){
  const panels=wizardPanels();
  const max=panels.length-1;
  currentWizardStep=Math.max(0,Math.min(step,max));
  panels.forEach((panel,index)=>panel.classList.toggle("active",index===currentWizardStep));
  document.querySelectorAll(".wizard-step").forEach((button,index)=>{
    button.classList.toggle("active",index===currentWizardStep);
    button.classList.toggle("completed",index<currentWizardStep);
  });
  document.getElementById("currentStepNumber").textContent=String(currentWizardStep+1);
  document.getElementById("prevStep").style.visibility=currentWizardStep===0?"hidden":"visible";
  document.getElementById("nextStep").classList.toggle("hidden",currentWizardStep===max);
  document.getElementById("submitBtn").classList.toggle("hidden",currentWizardStep!==max);
  window.scrollTo({top:0,behavior:"smooth"});
}
function validateCurrentVisualStep(){
  const panel=wizardPanels()[currentWizardStep];
  if(!panel)return true;
  const required=Array.from(panel.querySelectorAll("[required]")).filter(el=>!el.closest(".hidden"));
  for(const el of required){
    if(!el.checkValidity()){el.reportValidity();return false;}
  }
  return true;
}
document.getElementById("nextStep").addEventListener("click",()=>{if(validateCurrentVisualStep())showWizardStep(currentWizardStep+1);});
document.getElementById("prevStep").addEventListener("click",()=>showWizardStep(currentWizardStep-1));
document.querySelectorAll(".wizard-step").forEach(button=>{
  button.addEventListener("click",()=>{
    const target=Number(button.dataset.stepTarget||0);
    if(target<=currentWizardStep||validateCurrentVisualStep())showWizardStep(target);
  });
});
function syncOperationEmpty(){
  const crem=!document.getElementById("crematorioSection").classList.contains("hidden");
  const inh=!document.getElementById("inhumacionSection").classList.contains("hidden");
  document.getElementById("operationEmpty").classList.toggle("hidden",crem||inh);
}
servicio.addEventListener("change",syncOperationEmpty);
document.getElementById("resetBtn").addEventListener("click",()=>showWizardStep(0));
showWizardStep(0);
syncOperationEmpty();


/* Mejoras de fecha, multiselección e imagen */
function parseDisplayDate(value,withTime=true){
  if(!value)return null;
  let dt=null;

  // Native datetime-local: YYYY-MM-DDTHH:mm
  if(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(value)){
    dt=new Date(value);
    return Number.isNaN(dt.getTime())?null:dt;
  }

  // Native date: YYYY-MM-DD
  if(/^\d{4}-\d{2}-\d{2}$/.test(value)){
    const [y,m,d]=value.split("-").map(Number);
    dt=new Date(y,m-1,d,0,0,0,0);
    return Number.isNaN(dt.getTime())?null:dt;
  }

  // Legacy display fallback: dd/mm/yyyy HH:mm
  const parts=value.trim().split(" ");
  const d=parts[0].split("/");
  if(d.length!==3)return null;
  const day=Number(d[0]),month=Number(d[1])-1,year=Number(d[2]);
  let hour=0,minute=0;
  if(withTime&&parts[1]){
    const t=parts[1].split(":");
    hour=Number(t[0]||0);minute=Number(t[1]||0);
  }
  dt=new Date(year,month,day,hour,minute,0,0);
  return Number.isNaN(dt.getTime())?null:dt;
}

/* Selectores nativos date/datetime-local. */

calcAge=function(){
  const n=document.getElementById("fechaNacimiento").value;
  const d=document.getElementById("fechaDefuncion").value;
  const out=document.getElementById("edad");
  const birth=parseDisplayDate(n,false);
  const death=parseDisplayDate(d,true);
  if(!birth||!death){out.value="";return;}
  let age=death.getFullYear()-birth.getFullYear();
  const birthday=new Date(death.getFullYear(),birth.getMonth(),birth.getDate());
  if(birthday>death)age--;
  out.value=Math.max(0,age);
};
document.getElementById("fechaNacimiento").addEventListener("input",calcAge);
document.getElementById("fechaDefuncion").addEventListener("input",calcAge);

/* Dropdown multiselección */
const extrasMenu=document.getElementById("extrasMenu");
const extrasDropdown=document.getElementById("extrasDropdown");
const extrasToggle=document.getElementById("extrasToggle");
const extrasToggleText=document.getElementById("extrasToggleText");

function updateExtrasToggleLabel(){
  const selected=selectedExtras();
  if(!extrasToggleText)return;
  if(selected.length===0){
    extrasToggleText.textContent="Seleccionar servicios";
  }else if(selected.length===1){
    extrasToggleText.textContent=selected[0];
  }else{
    extrasToggleText.textContent=selected.length+" servicios seleccionados";
  }
}

function setExtrasDropdownOpen(open){
  if(!extrasMenu||!extrasToggle)return;
  extrasMenu.classList.toggle("hidden",!open);
  extrasToggle.setAttribute("aria-expanded",open?"true":"false");
  extrasDropdown?.classList.toggle("open",open);
}

extrasToggle?.addEventListener("click",e=>{
  e.preventDefault();
  e.stopPropagation();
  setExtrasDropdownOpen(extrasMenu.classList.contains("hidden"));
});

document.addEventListener("click",e=>{
  if(!extrasDropdown?.contains(e.target)) setExtrasDropdownOpen(false);
});

extrasMenu?.addEventListener("click",e=>e.stopPropagation());

function syncExtrasNativeFromChecks(){
  const checked=Array.from(extrasMenu.querySelectorAll('input[type="checkbox"]:checked')).map(i=>i.value);
  Array.from(extrasSelect.options).forEach(o=>o.selected=checked.includes(o.value));
  if(checked.includes("No Aplica")){
    Array.from(extrasMenu.querySelectorAll('input[type="checkbox"]')).forEach(i=>{
      if(i.value!=="No Aplica")i.checked=false;
    });
    Array.from(extrasSelect.options).forEach(o=>o.selected=o.value==="No Aplica");
  }
  const finalSelected=selectedExtras();
  updateExtras();
  updateExtrasToggleLabel();
}

EXTRAS.forEach(name=>{
  const label=document.createElement("label");
  label.className="multi-option";
  const check=document.createElement("input");
  check.type="checkbox";
  check.value=name;
  const text=document.createElement("span");
  text.textContent=name;
  label.append(check,text);
  extrasMenu.appendChild(label);
  check.addEventListener("change",()=>{
    if(name==="No Aplica"&&check.checked){
      extrasMenu.querySelectorAll('input[type="checkbox"]').forEach(i=>{if(i!==check)i.checked=false;});
    }else if(name!=="No Aplica"&&check.checked){
      const na=extrasMenu.querySelector('input[value="No Aplica"]');
      if(na)na.checked=false;
    }
    syncExtrasNativeFromChecks();
  });
});


/* Editor de esquela */
let cropper=null;
const esquelaInput=document.getElementById("esquelaInput");
const imageEditor=document.getElementById("imageEditor");
const cropImage=document.getElementById("cropImage");
const imageResult=document.getElementById("imageResult");
const croppedPreview=document.getElementById("croppedPreview");
const processedInput=document.getElementById("esquelaProcesada");

function openCropperFromFile(file){
  if(!file)return;
  const reader=new FileReader();
  reader.onload=()=>{
    cropImage.src=reader.result;
    imageEditor.classList.remove("hidden");
    imageResult.classList.add("hidden");
    if(cropper){cropper.destroy();cropper=null;}
    cropImage.onload=()=>{
      cropper=new Cropper(cropImage,{
        aspectRatio:4/5,
        viewMode:1,
        dragMode:"move",
        autoCropArea:.82,
        background:false,
        responsive:true,
        restore:false,
        guides:true,
        center:true,
        movable:true,
        zoomable:true,
        rotatable:true,
        scalable:false
      });
    };
  };
  reader.readAsDataURL(file);
}
esquelaInput.addEventListener("change",()=>openCropperFromFile(esquelaInput.files[0]));
document.getElementById("zoomOutBtn").addEventListener("click",()=>cropper&&cropper.zoom(-.1));
document.getElementById("zoomInBtn").addEventListener("click",()=>cropper&&cropper.zoom(.1));
document.getElementById("rotateBtn").addEventListener("click",()=>cropper&&cropper.rotate(90));
document.getElementById("resetCropBtn").addEventListener("click",()=>cropper&&cropper.reset());
document.getElementById("applyCropBtn").addEventListener("click",()=>{
  if(!cropper)return;
  const canvas=cropper.getCroppedCanvas({width:800,height:1000,imageSmoothingEnabled:true,imageSmoothingQuality:"high"});
  const data=canvas.toDataURL("image/jpeg",.9);
  processedInput.value=data;
  croppedPreview.src=data;
  imageEditor.classList.add("hidden");
  imageResult.classList.remove("hidden");
});
document.getElementById("editCropBtn").addEventListener("click",()=>{
  imageResult.classList.add("hidden");
  imageEditor.classList.remove("hidden");
});

document.getElementById("resetBtn").addEventListener("click",()=>{
  extrasMenu.querySelectorAll('input[type="checkbox"]').forEach(i=>i.checked=false);
  updateExtrasToggleLabel();
  setExtrasDropdownOpen(false);

  if(cropper){cropper.destroy();cropper=null;}
  imageEditor.classList.add("hidden");
  imageResult.classList.add("hidden");
  processedInput.value="";
  croppedPreview.removeAttribute("src");
});


/* Mostrar nombre de documentos seleccionados */
[
  ["certificadoDefuncion","certificadoDefuncionName"],
  ["ordenInhumacionCremacion","ordenInhumacionCremacionName"]
].forEach(([inputId,labelId])=>{
  const input=document.getElementById(inputId);
  const label=document.getElementById(labelId);
  if(input&&label){
    input.addEventListener("change",()=>{
      const file=input.files&&input.files[0];
      label.textContent=file?file.name:"PDF o imagen";
    });
  }
});




/* Fecha + hora separadas visualmente, un solo valor combinado para SharePoint */
function jdjpPad2(n){ return String(n).padStart(2,"0"); }

function jdjpDateToIso(dateText,timeText){
  if(!dateText || !timeText) return "";
  const p=dateText.split("/");
  if(p.length!==3) return "";
  const [dd,mm,yyyy]=p;
  if(!/^\d{2}$/.test(dd) || !/^\d{2}$/.test(mm) || !/^\d{4}$/.test(yyyy)) return "";
  if(!/^\d{2}:\d{2}$/.test(timeText)) return "";
  return yyyy+"-"+mm+"-"+dd+"T"+timeText+":00";
}

function jdjpIsoToParts(value){
  if(!value) return {date:"",time:""};
  // ISO/local SharePoint-style value
  let m=value.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
  if(m) return {date:m[3]+"/"+m[2]+"/"+m[1],time:m[4]+":"+m[5]};
  // Legacy display value
  m=value.match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/);
  if(m) return {date:m[1]+"/"+m[2]+"/"+m[3],time:m[4]+":"+m[5]};
  return {date:"",time:""};
}

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

function syncJdjpCombined(original,dateInput,timeInput){
  original.value=jdjpDateToIso(dateInput.value,timeInput.value);
  original.dispatchEvent(new Event("input",{bubbles:true}));
  original.dispatchEvent(new Event("change",{bubbles:true}));
}

function createJdjpSplitDateTime(original){
  if(!original || original.dataset.jdjpSplit==="1") return;
  original.dataset.jdjpSplit="1";

  const existing=jdjpIsoToParts(original.value);
  const wasRequired=original.required;
  original.required=false;
  original.type="hidden";
  original.classList.remove("datetime-picker");

  const wrap=document.createElement("div");
  wrap.className="split-datetime";

  const dateWrap=document.createElement("div");
  dateWrap.className="split-datetime-part split-date-part";
  const dateLabel=document.createElement("span");
  dateLabel.className="split-datetime-label";
  dateLabel.textContent="Fecha";
  const dateInput=document.createElement("input");
  dateInput.type="text";
  dateInput.className="split-date-input";
  dateInput.placeholder="dd/mm/yyyy";
  dateInput.autocomplete="off";
  dateInput.value=existing.date;

  const timeWrap=document.createElement("div");
  timeWrap.className="split-datetime-part split-time-part";
  const timeLabel=document.createElement("span");
  timeLabel.className="split-datetime-label";
  timeLabel.textContent="Hora";
  const timeInput=document.createElement("input");
  timeInput.type="time";
  timeInput.className="split-time-input";
  timeInput.step="300";
  timeInput.value=existing.time;

  dateWrap.append(dateLabel,dateInput);
  timeWrap.append(timeLabel,timeInput);
  wrap.append(dateWrap,timeWrap);
  original.after(wrap);

  original.dataset.dateInputId=original.id+"__date";
  original.dataset.timeInputId=original.id+"__time";
  dateInput.id=original.dataset.dateInputId;
  timeInput.id=original.dataset.timeInputId;

  if(wasRequired){
    dateInput.required=true;
    timeInput.required=true;
  }

  applyJdjpDateOnlyMask(dateInput);

  if(window.flatpickr){
    flatpickr(dateInput,{
      enableTime:false,
      dateFormat:"d/m/Y",
      allowInput:true,
      disableMobile:true,
      locale:"es",
      onChange:()=>{
        syncJdjpCombined(original,dateInput,timeInput);
        if(original.id==="fechaDefuncion") calcAge();
      }
    });
  }

  const sync=()=>{
    syncJdjpCombined(original,dateInput,timeInput);
    if(original.id==="fechaDefuncion") calcAge();
  };
  dateInput.addEventListener("input",sync);
  dateInput.addEventListener("change",sync);
  timeInput.addEventListener("input",sync);
  timeInput.addEventListener("change",sync);
}

function initJdjpDatePickers(){
  if(window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.es){
    flatpickr.localize(flatpickr.l10ns.es);
  }

  document.querySelectorAll(".datetime-picker").forEach(createJdjpSplitDateTime);

  document.querySelectorAll(".date-only-picker").forEach(el=>{
    applyJdjpDateOnlyMask(el);
    if(window.flatpickr){
      if(el._flatpickr) el._flatpickr.destroy();
      flatpickr(el,{
        enableTime:false,
        dateFormat:"d/m/Y",
        allowInput:true,
        disableMobile:true,
        locale:"es",
        onChange:()=>{ if(el.id==="fechaNacimiento") calcAge(); }
      });
    }
  });
}
initJdjpDatePickers();

/* Hacer que Required se aplique a las dos partes visibles */
const jdjpOriginalSetRequired=setRequired;
setRequired=function(id,on){
  const el=document.getElementById(id);
  if(!el) return;

  if(el.dataset.jdjpSplit==="1"){
    const d=document.getElementById(el.dataset.dateInputId);
    const t=document.getElementById(el.dataset.timeInputId);
    if(d) d.required=!!on;
    if(t) t.required=!!on;
    el.required=false;
    return;
  }

  jdjpOriginalSetRequired(id,on);
};

/* Reaplicar reglas una vez creados los controles separados */
updateRules();


/* Integración SharePoint: Eventos Capillas */
function rsMoneyNumber(text){
  const n=Number(String(text||"").replace(/[^0-9.-]/g,""));
  return Number.isFinite(n)?n:0;
}

function rsPayload(){
  const data=new FormData(form);
  const servicioActual=String(data.get("servicio")||"").trim();
  const sinVelacion=servicioActual==="Cremación Directa (sin velación)";
  const servicioCremacion=["Cremación","Cremación Directa (con velación)","Cremación Directa (sin velación)"].includes(servicioActual);
  const extrasSeleccionados=selectedExtras();
  // SharePoint tiene "Servicios Extra" como obligatorio.
  // Si el usuario no selecciona adicionales, registrar explicitamente "No Aplica".
  const extras=extrasSeleccionados.length?extrasSeleccionados:["No Aplica"];
  const extraAmounts={};
  document.querySelectorAll("[data-extra-input]").forEach(input=>{
    const key=input.dataset.extraInput;
    if(key) extraAmounts[key]=input.value===""?null:Number(input.value);
  });
  return {
    numeroReferencia:String(data.get("numeroReferencia")||"").trim(),
    servicio:servicioActual,
    ubicacion:String(data.get("ubicacion")||"").trim(),
    // Cremacion directa sin velacion no usa datos de capilla ni crea evento de velacion.
    sala:sinVelacion?"":String(data.get("sala")||"").trim(),
    inicio:sinVelacion?"":String(document.getElementById("inicio")?.value||""),
    termino:sinVelacion?"":String(document.getElementById("termino")?.value||""),
    llevaExequia:sinVelacion?false:document.getElementById("llevaExequia")?.checked===true,
    tiempoCapillas:sinVelacion?"":String(data.get("tiempoCapillas")||"").trim(),
    horaExequia:sinVelacion?"":String(document.getElementById("horaExequia")?.value||""),
    prevision:String(data.get("prevision")||"").trim(),
    tipoAtaud:String(data.get("tipoAtaud")||"").trim(),
    numeroServicio:String(data.get("numeroServicio")||"").trim(),
    codigoServicio:String(document.getElementById("codigoServicio")?.textContent||"").replace("—","").trim(),
    codigoAtaud:String(document.getElementById("codigoAtaud")?.textContent||"").replace("—","").trim(),
    referencia:String(document.getElementById("referenciaPreview")?.textContent||"").replace("—","").trim(),
    requierePlaca:servicioCremacion || document.getElementById("requierePlaca")?.checked===true,
    titular:String(data.get("titular")||"").trim(),
    fallecido:String(data.get("fallecido")||"").trim(),
    fechaNacimiento:String(document.getElementById("fechaNacimiento")?.value||""),
    fechaDefuncion:String(document.getElementById("fechaDefuncion")?.value||""),
    sexo:String(data.get("sexo")||"").trim(),
    edad:String(document.getElementById("edad")?.value||""),
    destinoFinal:String(data.get("destinoFinal")||"").trim(),
    embalsamador:String(data.get("embalsamador")||"").trim(),
    rescate1:String(data.get("rescate1")||"").trim(),
    rescate2:String(data.get("rescate2")||"").trim(),
    ubicacionRescate:String(data.get("ubicacionRescate")||"").trim(),
    motivo:String(data.get("motivo")||"").trim(),
    referenciaCrematorio:String(data.get("referenciaCrematorio")||"").trim(),
    inicioCrematorio:String(document.getElementById("inicioCrematorio")?.value||""),
    personalCrematorio:String(data.get("personalCrematorio")||"").trim(),
    fechaHoraInhumacion:String(document.getElementById("fechaHoraInhumacion")?.value||""),
    fechaCompra:String(document.getElementById("fechaCompra")?.value||""),
    personalVenta:String(data.get("personalVenta")||"").trim(),
    precioVenta:data.get("precioVenta")===""?null:Number(data.get("precioVenta")),
    serviciosExtra:extras,
    extraAmounts,
    ventaTotal:rsMoneyNumber(document.getElementById("ventaTotal")?.textContent||"0")
  };
}

async function rsCroppedFile(){
  const value=String(document.getElementById("esquelaProcesada")?.value||"");
  if(!value.startsWith("data:image/")) return null;
  const response=await fetch(value);
  const blob=await response.blob();
  return new File([blob],"Imagen_Esquela.jpg",{type:blob.type||"image/jpeg"});
}

async function rsSubmitToSharePoint(){
  const button=document.getElementById("submitBtn");
  const status=document.getElementById("status");
  if(!button)return;

  const original=button.textContent;
  button.disabled=true;
  button.textContent="Registrando...";
  if(status) status.textContent="Enviando información a SharePoint...";

  try{
    const body=new FormData();
    body.append("payload",JSON.stringify(rsPayload()));
    const draftId=document.getElementById("draftId")?.value||"";
    if(draftId) body.append("draftId",draftId);

    const cropped=await rsCroppedFile();
    if(cropped) body.append("esquelaProcesadaFile",cropped,cropped.name);

    const cert=document.getElementById("certificadoDefuncion")?.files?.[0];
    if(cert) body.append("certificadoDefuncion",cert,cert.name);

    const orden=document.getElementById("ordenInhumacionCremacion")?.files?.[0];
    if(orden) body.append("ordenInhumacionCremacion",orden,orden.name);

    const response=await fetch("registro-servicio.php",{
      method:"POST",
      body,
      credentials:"same-origin",
      headers:{"Accept":"application/json"}
    });
    const result=await response.json().catch(()=>null);
    if(!response.ok || !result?.ok){
      throw new Error(result?.message||("HTTP "+response.status));
    }

    console.log("Resultado completo Registro Servicios:",result);

    const plate=result?.plate||null;
    const plateEmail=result?.plateEmail||null;
    const letter=result?.letter||null;
    const email=result?.email||null;
    const tellmebye=result?.tellmebye||null;
    const calendar=result?.calendar||null;

    const lines=[
      "Servicio registrado correctamente en SharePoint.",
      "ID: "+result.itemId
    ];

    if(plate?.required===true){
      if(plate?.savedToSharePoint===true){
        lines.push("Placa: OK - "+(plate.fileName||"PNG"));
      }else{
        lines.push("Placa: ERROR"+(plate?.error?(" - "+plate.error):""));
      }
    }else{
      lines.push("Placa: no requerida");
    }

    if(plateEmail?.sent===true){
      lines.push("Correo de placa: ENVIADO");
    }else if(plateEmail?.enabled===true){
      lines.push("Correo de placa: ERROR"+(plateEmail?.error?(" - "+plateEmail.error):""));
    }

    if(letter?.required===true){
      if(letter?.attachedToSharePoint===true || letter?.created===true){
        lines.push("Carta: OK"+(letter?.fileName?(" - "+letter.fileName):""));
      }else{
        lines.push("Carta: ERROR"+(letter?.error?(" - "+letter.error):""));
      }
    }

    if(calendar?.skipped===true){
      lines.push("Calendario: OMITIDO (prueba controlada)");
    }else if(calendar?.created===true){
      lines.push("Calendario: CREADO");
    }else if(calendar?.enabled===false){
      lines.push("Calendario: deshabilitado");
    }

    if(email?.sent===true){
      lines.push("Correo: ENVIADO");
      if(Array.isArray(email?.recipients) && email.recipients.length){
        lines.push("Destinatarios: "+email.recipients.join(", "));
      }
      if(Array.isArray(email?.attachmentNames) && email.attachmentNames.length){
        lines.push("Adjuntos enviados ("+email.attachmentNames.length+"): "+email.attachmentNames.join(", "));
      }
    }else if(email?.enabled===true){
      lines.push("Correo: ERROR");
      lines.push("Detalle correo: "+(email?.error||"El servidor no confirmó el envío."));
    }else{
      lines.push("Correo: no habilitado");
    }

    if(tellmebye?.triggered===true){
      lines.push("TellMeBye: DISPARADO ("+(tellmebye.mode||"modo no indicado")+")");
      if(tellmebye.mode==="publicar"){
        lines.push("Esquela: se enviará en correo independiente al terminar TellMeBye");
      }else{
        lines.push("Esquela: modo preview, no se publica ni envía cartulina final");
      }
    }else if(tellmebye?.enabled===true){
      lines.push("TellMeBye: NO DISPARADO"+(tellmebye?.error?(" - "+tellmebye.error):""));
    }else{
      lines.push("TellMeBye: no habilitado");
    }

    const finalMessage=lines.join("\n");
    if(status) status.textContent=lines.join(" | ");
    alert(finalMessage);

    button.textContent="Registrado";
  }catch(error){
    console.error("Registro SharePoint:",error);
    if(status) status.textContent="No fue posible registrar: "+(error?.message||error);
    button.disabled=false;
    button.textContent=original;
    return;
  }
}

form.addEventListener("submit",async e=>{
  e.preventDefault();
  if(!validateCurrentVisualStep()) return;
  await rsSubmitToSharePoint();
});


/* Borradores de Registro de Servicios */
function rsDraftFormData(){
  const body=new FormData();
  body.append("payload",JSON.stringify(rsPayload()));
  const current=document.getElementById("draftId")?.value||"";
  if(current) body.append("draftId",current);
  return body;
}

async function rsAddDraftFiles(body){
  const cropped=await rsCroppedFile();
  if(cropped) body.append("esquelaProcesadaFile",cropped,cropped.name);
  const cert=document.getElementById("certificadoDefuncion")?.files?.[0];
  if(cert) body.append("certificadoDefuncion",cert,cert.name);
  const orden=document.getElementById("ordenInhumacionCremacion")?.files?.[0];
  if(orden) body.append("ordenInhumacionCremacion",orden,orden.name);
}

async function rsSaveDraft(){
  const button=document.getElementById("saveDraftBtn");
  const status=document.getElementById("status");
  if(!button)return;
  const original=button.textContent;
  button.disabled=true;
  button.textContent="Guardando...";
  try{
    const body=rsDraftFormData();
    await rsAddDraftFiles(body);
    const response=await fetch("guardar-borrador.php",{method:"POST",body,credentials:"same-origin",headers:{"Accept":"application/json"}});
    const result=await response.json().catch(()=>null);
    if(!response.ok||!result?.ok) throw new Error(result?.message||("HTTP "+response.status));
    document.getElementById("draftId").value=result.draftId||"";
    const url=new URL(location.href);
    url.searchParams.set("draft",result.draftId);
    url.searchParams.delete("nuevo");
    history.replaceState(null,"",url);
    if(status) status.textContent="Borrador guardado. Puedes continuar después desde Mis servicios.";
    button.textContent="Borrador guardado";
    setTimeout(()=>{button.textContent=original;button.disabled=false;},1500);
  }catch(error){
    console.error("Guardar borrador:",error);
    if(status) status.textContent="No fue posible guardar el borrador: "+(error?.message||error);
    button.textContent=original;
    button.disabled=false;
  }
}
document.getElementById("saveDraftBtn")?.addEventListener("click",rsSaveDraft);

function rsSetNamed(name,value){
  const el=form.elements.namedItem(name);
  if(!el)return;
  if(el instanceof RadioNodeList){
    el.value=value??"";
    return;
  }
  if(el.type==="checkbox"){
    el.checked=!!value;
  }else{
    el.value=value??"";
    el.dispatchEvent(new Event("change",{bubbles:true}));
  }
}

function rsSetDateTime(id,value){
  const original=document.getElementById(id);
  if(!original)return;
  original.value=value||"";
  if(original.dataset.jdjpSplit==="1"){
    const parts=jdjpIsoToParts(value||"");
    const d=document.getElementById(original.dataset.dateInputId);
    const t=document.getElementById(original.dataset.timeInputId);
    if(d)d.value=parts.date;
    if(t)t.value=parts.time;
  }
}

function rsApplyDraftPayload(p){
  if(!p||typeof p!=="object")return;
  const simple=["numeroReferencia","servicio","ubicacion","sala","tiempoCapillas","prevision","tipoAtaud","numeroServicio","titular","fallecido","sexo","destinoFinal","embalsamador","rescate1","rescate2","ubicacionRescate","motivo","referenciaCrematorio","personalCrematorio","personalVenta","precioVenta"];
  simple.forEach(k=>rsSetNamed(k,p[k]??""));
  document.getElementById("llevaExequia").checked=!!p.llevaExequia;
  document.getElementById("requierePlaca").checked=!!p.requierePlaca;

  ["inicio","termino","horaExequia","fechaDefuncion","inicioCrematorio","fechaHoraInhumacion"].forEach(k=>rsSetDateTime(k,p[k]||""));
  const nacimiento=document.getElementById("fechaNacimiento");
  if(nacimiento)nacimiento.value=p.fechaNacimiento||"";
  const compra=document.getElementById("fechaCompra");
  if(compra)compra.value=p.fechaCompra||"";

  const selected=Array.isArray(p.serviciosExtra)?p.serviciosExtra:[];
  extrasMenu.querySelectorAll('input[type="checkbox"]').forEach(ch=>ch.checked=selected.includes(ch.value));
  Array.from(extrasSelect.options).forEach(o=>o.selected=selected.includes(o.value));
  updateExtras();
  const amounts=p.extraAmounts&&typeof p.extraAmounts==="object"?p.extraAmounts:{};
  document.querySelectorAll("[data-extra-input]").forEach(i=>{const v=amounts[i.dataset.extraInput];i.value=(v===null||v===undefined)?"":String(v);});
  updateRules();updateExtras();updateTotal();calcAge();syncOperationEmpty();
}

async function rsLoadDraftFromUrl(){
  const params=new URLSearchParams(location.search);
  const id=params.get("draft");
  if(!id)return;
  const status=document.getElementById("status");
  try{
    if(status)status.textContent="Cargando borrador...";
    const response=await fetch("guardar-borrador.php?id="+encodeURIComponent(id),{cache:"no-store",credentials:"same-origin"});
    const result=await response.json().catch(()=>null);
    if(!response.ok||!result?.ok)throw new Error(result?.message||("HTTP "+response.status));
    document.getElementById("draftId").value=id;
    rsApplyDraftPayload(result.draft?.payload||{});
    const files=result.draft?.files||{};
    if(files.certificado?.name)document.getElementById("certificadoDefuncionName").textContent=files.certificado.name+" · guardado";
    if(files.orden?.name)document.getElementById("ordenInhumacionCremacionName").textContent=files.orden.name+" · guardado";
    if(files.esquela?.name){
      const resultBox=document.getElementById("imageResult");
      if(resultBox){
        resultBox.classList.remove("hidden");
        const copy=resultBox.querySelector("span");
        if(copy)copy.textContent="La imagen ajustada ya está guardada en este borrador.";
      }
    }
    if(status)status.textContent="Borrador cargado. Continúa la captura o publícalo cuando esté completo.";
  }catch(error){
    console.error("Cargar borrador:",error);
    if(status)status.textContent="No fue posible cargar el borrador: "+(error?.message||error);
  }
}
rsLoadDraftFromUrl();
