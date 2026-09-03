const sceneHost=document.getElementById('mobility-scene');
const canvas=document.getElementById('hero-canvas');
const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const coarse=window.matchMedia('(pointer: coarse)').matches;
let cleanupScene=()=>{};

const loginEl=document.getElementById('loginModal');
const signupEl=document.getElementById('signupModal');
const loginModal=bootstrap.Modal.getOrCreateInstance(loginEl);
const signupModal=bootstrap.Modal.getOrCreateInstance(signupEl);
document.querySelectorAll('[data-open-login]').forEach(el=>el.addEventListener('click',()=>loginModal.show()));
document.querySelectorAll('[data-open-signup]').forEach(el=>el.addEventListener('click',()=>signupModal.show()));
document.querySelector('[data-switch-signup]')?.addEventListener('click',()=>{loginModal.hide();setTimeout(()=>signupModal.show(),180)});
document.querySelector('[data-switch-login]')?.addEventListener('click',()=>{signupModal.hide();setTimeout(()=>loginModal.show(),180)});
window.addEventListener('scroll',()=>document.querySelector('.site-nav')?.classList.toggle('scrolled',scrollY>12),{passive:true});
document.querySelectorAll('#mainNav a').forEach(link=>link.addEventListener('click',()=>bootstrap.Collapse.getInstance(document.getElementById('mainNav'))?.hide()));

function submitAuth(form,successTitle){
  form?.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button[type=submit]');button.disabled=true;try{const response=await fetch('',{method:'POST',body:new FormData(form)});const data=await response.json();if(data.status==='success'){await Swal.fire({icon:'success',title:successTitle,text:data.message,showConfirmButton:false,timer:1300});if(data.redirect){location.href=data.redirect}else{signupModal.hide();loginModal.show();form.reset()}}else{Swal.fire({icon:'error',title:'Please check your details',text:data.message,confirmButtonColor:'#2563EB'})}}catch(error){console.error('Authentication request failed',error);Swal.fire({icon:'error',title:'Unable to continue',text:'Please try again in a moment.',confirmButtonColor:'#2563EB'})}finally{button.disabled=false}})
}
submitAuth(document.getElementById('loginForm'),'Welcome back');
submitAuth(document.getElementById('signupForm'),'Account created');

async function initScene(){
  if(!sceneHost||!canvas||sceneHost.dataset.initialized)return;
  sceneHost.dataset.initialized='true';
  try{
    const THREE=await import('https://cdn.jsdelivr.net/npm/three@0.164.1/build/three.module.js');
    const test=document.createElement('canvas');
    if(!test.getContext('webgl2')&&!test.getContext('webgl'))throw new Error('WebGL unavailable');
    const renderer=new THREE.WebGLRenderer({canvas,antialias:!coarse,alpha:true,powerPreference:'high-performance'});
    renderer.setPixelRatio(Math.min(devicePixelRatio,coarse?1.25:1.75));
    renderer.setClearColor(0xeef5ff,1);
    const scene=new THREE.Scene();
    const camera=new THREE.PerspectiveCamera(38,1,.1,100);
    camera.position.set(9,11,12);camera.lookAt(0,0,0);
    scene.add(new THREE.HemisphereLight(0xffffff,0x94a3b8,2.2));
    const sun=new THREE.DirectionalLight(0xffffff,2.4);sun.position.set(5,10,7);scene.add(sun);
    const group=new THREE.Group();scene.add(group);
    const geometries=[];const materials=[];
    const material=color=>{const m=new THREE.MeshStandardMaterial({color,roughness:.75,metalness:.05});materials.push(m);return m};
    const navy=material(0x0f172a),slate=material(0x1e293b),road=material(0xdbe4ef),ground=material(0xf8fafc);
    const baseGeo=new THREE.BoxGeometry(18,.25,13);geometries.push(baseGeo);const base=new THREE.Mesh(baseGeo,ground);base.position.y=-.2;group.add(base);
    const roadGeo=new THREE.BoxGeometry(18,.06,2.2);geometries.push(roadGeo);const roadOne=new THREE.Mesh(roadGeo,road);roadOne.position.y=0;group.add(roadOne);const roadTwo=new THREE.Mesh(roadGeo,road);roadTwo.rotation.y=Math.PI/2;roadTwo.position.y=.01;group.add(roadTwo);
    const count=coarse?12:22;
    const buildingGeo=new THREE.BoxGeometry(1,1,1);geometries.push(buildingGeo);
    for(let i=0;i<count;i++){let x=(i%6-2.5)*2.55,z=(Math.floor(i/6)-1.5)*3;if(Math.abs(x)<1.6||Math.abs(z)<1.5)continue;const h=1.1+(i%4)*.65;const b=new THREE.Mesh(buildingGeo,i%3===0?navy:slate);b.scale.set(1.25,h,1.35);b.position.set(x,h/2,z);group.add(b)}
    const routePoints=[new THREE.Vector3(-8,.18,0),new THREE.Vector3(-3,.18,0),new THREE.Vector3(0,.18,0),new THREE.Vector3(0,.18,4.8),new THREE.Vector3(7,.18,4.8)];
    const curve=new THREE.CatmullRomCurve3(routePoints,false,'catmullrom',.08);
    const routeGeo=new THREE.TubeGeometry(curve,coarse?35:60,.075,6,false);geometries.push(routeGeo);const routeMat=new THREE.MeshBasicMaterial({color:0x2563eb});materials.push(routeMat);group.add(new THREE.Mesh(routeGeo,routeMat));
    const activeCurve=new THREE.CatmullRomCurve3([new THREE.Vector3(0,.2,-6),new THREE.Vector3(0,.2,0),new THREE.Vector3(0,.2,4.8)]);
    const activeGeo=new THREE.TubeGeometry(activeCurve,24,.055,6,false);geometries.push(activeGeo);const activeMat=new THREE.MeshBasicMaterial({color:0x06b6d4});materials.push(activeMat);group.add(new THREE.Mesh(activeGeo,activeMat));
    const markerGeo=new THREE.SphereGeometry(.22,12,12);geometries.push(markerGeo);
    [[-7.4,0x22c55e],[6.6,0xf59e0b]].forEach(([x,color],i)=>{const m=new THREE.MeshBasicMaterial({color});materials.push(m);const node=new THREE.Mesh(markerGeo,m);node.position.set(x,.35,i?4.8:0);group.add(node)});
    const vehicleGroup=new THREE.Group();const bodyGeo=new THREE.BoxGeometry(1.15,.48,.58),roofGeo=new THREE.BoxGeometry(.75,.3,.55),wheelGeo=new THREE.CylinderGeometry(.12,.12,.09,10);geometries.push(bodyGeo,roofGeo,wheelGeo);const vehicleMat=material(0x0f172a),windowMat=new THREE.MeshBasicMaterial({color:0x06b6d4});materials.push(windowMat);const wheelMat=material(0x334155);const body=new THREE.Mesh(bodyGeo,vehicleMat);body.position.y=.35;vehicleGroup.add(body);const roof=new THREE.Mesh(roofGeo,windowMat);roof.position.y=.7;vehicleGroup.add(roof);[-.35,.35].forEach(x=>[-.31,.31].forEach(z=>{const w=new THREE.Mesh(wheelGeo,wheelMat);w.rotation.x=Math.PI/2;w.position.set(x,.18,z);vehicleGroup.add(w)}));group.add(vehicleGroup);
    let pointerX=0,pointerY=0,frame=0,running=!document.hidden;
    const onPointer=e=>{if(coarse||reduced)return;const r=sceneHost.getBoundingClientRect();pointerX=((e.clientX-r.left)/r.width-.5)*.55;pointerY=((e.clientY-r.top)/r.height-.5)*.35};sceneHost.addEventListener('pointermove',onPointer,{passive:true});
    const resize=()=>{const w=sceneHost.clientWidth,h=sceneHost.clientHeight;renderer.setSize(w,h,false);camera.aspect=w/h;camera.updateProjectionMatrix()};const observer=new ResizeObserver(resize);observer.observe(sceneHost);resize();
    const visibility=()=>{running=!document.hidden;if(running&&!frame)render()};document.addEventListener('visibilitychange',visibility);
    const render=()=>{if(!running){frame=0;return}const time=performance.now()*.00006;const t=reduced?.38:(time%1);const pos=curve.getPointAt(t),ahead=curve.getPointAt(Math.min(t+.015,1));vehicleGroup.position.copy(pos);vehicleGroup.position.y=.16;vehicleGroup.lookAt(ahead.x,ahead.y+.16,ahead.z);camera.position.x+=(9+pointerX-camera.position.x)*.025;camera.position.y+=(11-pointerY-camera.position.y)*.025;camera.lookAt(0,0,0);renderer.render(scene,camera);frame=requestAnimationFrame(render)};
    sceneHost.classList.add('webgl-ready');render();
    cleanupScene=()=>{running=false;if(frame)cancelAnimationFrame(frame);observer.disconnect();document.removeEventListener('visibilitychange',visibility);sceneHost.removeEventListener('pointermove',onPointer);geometries.forEach(g=>g.dispose());materials.forEach(m=>m.dispose());renderer.dispose()};
  }catch(error){console.warn('Using static mobility illustration:',error.message);sceneHost.classList.add('webgl-fallback')}
}
const sceneObserver=new IntersectionObserver(entries=>{if(entries.some(entry=>entry.isIntersecting)){sceneObserver.disconnect();initScene()}},{rootMargin:'180px'});if(sceneHost)sceneObserver.observe(sceneHost);
window.addEventListener('pagehide',()=>cleanupScene(),{once:true});
