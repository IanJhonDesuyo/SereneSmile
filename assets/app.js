document.querySelector('.menu')?.addEventListener('click',()=>document.body.classList.toggle('nav-open'));
document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault()}));
document.querySelectorAll('[data-password-toggle]').forEach(btn=>btn.addEventListener('click',()=>{const input=document.getElementById(btn.dataset.passwordToggle);input.type=input.type==='password'?'text':'password';btn.textContent=input.type==='password'?'Show':'Hide'}));
document.querySelectorAll('.alert').forEach(el=>setTimeout(()=>el.classList.add('fade'),5000));
