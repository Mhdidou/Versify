<!-- Theme Toggle: Dark/Light Mode -->
<style>
.light-mode body { background-color: #f8fafc !important; color: #1e293b !important; }
.light-mode nav { background: rgba(248,250,252,0.9) !important; border-color: #e2e8f0 !important; }
.light-mode .glass-card { background: rgba(255,255,255,0.8) !important; border-color: #e2e8f0 !important; }
.light-mode .bg-slate-950, .light-mode .bg-slate-900 { background-color: #f1f5f9 !important; }
.light-mode .bg-slate-950\/80, .light-mode .bg-slate-900\/80 { background-color: rgba(241,245,249,0.9) !important; }
.light-mode .bg-slate-800 { background-color: #e2e8f0 !important; }
.light-mode .bg-slate-800\/50, .light-mode .bg-slate-800\/30 { background-color: rgba(226,232,240,0.4) !important; }
.light-mode .text-slate-100, .light-mode .text-slate-200 { color: #0f172a !important; }
.light-mode .text-slate-300 { color: #334155 !important; }
.light-mode .text-slate-400 { color: #64748b !important; }
.light-mode .text-slate-500, .light-mode .text-slate-600 { color: #94a3b8 !important; }
.light-mode .border-slate-800, .light-mode .border-slate-900 { border-color: #e2e8f0 !important; }
.light-mode .border-slate-700 { border-color: #cbd5e1 !important; }
.light-mode .divide-slate-800\/60 > :not([hidden]) ~ :not([hidden]) { border-color: rgba(226,232,240,0.6) !important; }
.light-mode input, .light-mode textarea, .light-mode select { background-color: #fff !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
.light-mode input:focus, .light-mode textarea:focus, .light-mode select:focus { border-color: #6366f1 !important; }
.light-mode footer { border-color: #e2e8f0 !important; }
.light-mode .sidebar-link.active { background: rgba(99,102,241,0.08) !important; }
.light-mode .hover\:bg-slate-800\/30:hover { background-color: rgba(226,232,240,0.4) !important; }
#theme-toggle-btn { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; border: 1px solid; }
.light-mode #theme-toggle-btn { background: #fff; border-color: #e2e8f0; color: #475569; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.light-mode #theme-toggle-btn:hover { border-color: #6366f1; color: #6366f1; }
:not(.light-mode) #theme-toggle-btn { background: rgba(30,41,59,0.8); border-color: #334155; color: #94a3b8; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
:not(.light-mode) #theme-toggle-btn:hover { border-color: #6366f1; color: #6366f1; }
</style>

<button id="theme-toggle-btn" title="Changer le theme" aria-label="Changer le theme">
    <svg id="icon-sun" class="w-5 h-5" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
    <svg id="icon-moon" class="w-5 h-5" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
</button>

<script>
(function(){
    var key='versify_theme';
    function get(){return localStorage.getItem(key)||'dark';}
    function apply(t){
        if(t==='light'){document.documentElement.classList.add('light-mode');}
        else{document.documentElement.classList.remove('light-mode');}
        document.getElementById('icon-sun').style.display=t==='dark'?'block':'none';
        document.getElementById('icon-moon').style.display=t==='light'?'block':'none';
    }
    apply(get());
    document.getElementById('theme-toggle-btn').addEventListener('click',function(){
        var next=get()==='dark'?'light':'dark';
        localStorage.setItem(key,next);
        apply(next);
    });
})();
</script>
