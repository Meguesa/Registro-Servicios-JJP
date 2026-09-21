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
