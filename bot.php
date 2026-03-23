<?php
// ── DEBUG: شيل الـ # من السطر اللي تحت لو عايز تشوف تفاصيل الخطأ ──
// error_reporting(E_ALL); ini_set('display_errors', 1);

define('GEMINI_KEY', 'AIzaSyCWpC_xqTGsloDeOUPNg00-c6a47YPIWwk');
define('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_KEY);
define('SYS_PROMPT', 'أنت مساعد روحي وداعم لمنصة Daily Bible SPS. ترد بالعربي المصري البسيط والودود. ردودك قصيرة وطبيعية. لو المستخدم حزين واسيه، لو مضغوط هدّيه، لو سأل عن آية اقترحلوا آية. مش بتدي فتاوى. لو الموضوع محتاج كاهن قوله: ممكن تسأل أب اعترافك. لو اقترحت آية اكتبها في النهاية بالصيغة: [VERSE:المرجع:نص الآية]');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // امسح أي output قبل الـ headers
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');

    $raw   = file_get_contents('php://input');
    $body  = json_decode($raw, true);
    $msgs  = $body['messages'] ?? [];

    if (empty($msgs)) {
        echo json_encode(['error' => 'no_messages']);
        exit;
    }

    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => SYS_PROMPT]]],
        'contents'           => $msgs,
        'generationConfig'   => ['maxOutputTokens' => 500, 'temperature' => 0.85]
    ], JSON_UNESCAPED_UNICODE);

    $result   = false;
    $method   = 'none';
    $last_err = '';

    // ══ محاولة 1: cURL ══
    if (function_exists('curl_init')) {
        $ch = curl_init(GEMINI_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $result   = curl_exec($ch);
        $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $last_err = curl_error($ch);
        curl_close($ch);

        if ($result && $http === 200) {
            $method = 'curl_ok';
        } else {
            // cURL اشتغل بس مش 200
            if ($result && $http !== 200) {
                echo json_encode([
                    'error'   => 'curl_http_' . $http,
                    'details' => substr($result, 0, 300)
                ]);
                exit;
            }
            $last_err = 'curl_failed: ' . $last_err;
            $result   = false;
        }
    }

    // ══ محاولة 2: file_get_contents ══
    if ($result === false && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n",
                'content'       => $payload,
                'timeout'       => 25,
                'ignore_errors' => true,
            ],
            'ssl'  => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ]
        ]);
        $result = @file_get_contents(GEMINI_URL, false, $ctx);
        if ($result !== false) $method = 'fgc_ok';
        else $last_err .= ' | fgc_failed';
    }

    // ══ فشل الاتنين ══
    if ($result === false || $result === '') {
        // اعرف هل allow_url_fopen شغال
        $fopen  = ini_get('allow_url_fopen')  ? 'on'  : 'off';
        $curl   = function_exists('curl_init') ? 'yes' : 'no';
        echo json_encode([
            'error'         => 'all_methods_failed',
            'curl'          => $curl,
            'allow_url_fopen' => $fopen,
            'last_error'    => $last_err,
            'tip'           => 'InfinityFree is blocking outbound HTTP. See message for solution.'
        ]);
        exit;
    }

    // ══ تحقق إن الرد JSON صحيح ══
    $decoded = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'error' => 'invalid_json_from_gemini',
            'method' => $method,
            'raw'   => base64_encode(substr($result, 0, 400))
        ]);
        exit;
    }

    // ══ نجح! ══
    echo $result;
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Bible SPS – المساعد الروحي</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--p:#06b6d4;--pd:#0891b2;--pg:rgba(6,182,212,.18);--bg:#09090b;--bg2:#18181b;--bg3:#27272a;--s2:#2a2a2e;--br:#3f3f46;--t:#fafafa;--t2:#a1a1aa;--t3:#71717a;--gl:#fbbf24}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;font-family:'Cairo',sans-serif;background:var(--bg);color:var(--t);direction:rtl}
.app{display:flex;height:100vh;overflow:hidden}
.sidebar{width:270px;flex-shrink:0;background:var(--bg2);border-left:1px solid var(--br);display:flex;flex-direction:column;padding:1.25rem .9rem;gap:.85rem}
.logo-area{display:flex;align-items:center;gap:.7rem}
.logo-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#155e75,#0891b2);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.logo-text h1{font-size:.95rem;font-weight:800;line-height:1.2}.logo-text p{font-size:.7rem;color:var(--t2);font-weight:600}
.div{height:1px;background:var(--br);margin:.15rem 0}
.slbl{font-size:.68rem;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em}
.qb{display:flex;align-items:center;gap:.55rem;padding:.6rem .7rem;border-radius:.55rem;background:transparent;border:none;color:var(--t2);font-family:'Cairo',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;width:100%;text-align:right;transition:background .2s,color .2s}
.qb:hover{background:var(--bg3);color:var(--t)}.qb i{color:var(--p);width:17px;text-align:center;font-size:.88rem}
.sbot{margin-top:auto}.mbar{background:var(--bg3);border-radius:.55rem;padding:.7rem}
.mbar p{font-size:.76rem;color:var(--t2);margin-bottom:.45rem;font-weight:600}
.memojis{display:flex;justify-content:space-around}
.mb{background:none;border:1.5px solid transparent;border-radius:8px;padding:.28rem .38rem;font-size:1.15rem;cursor:pointer;transition:border-color .2s,background .2s}
.mb:hover{border-color:var(--p);background:var(--pg)}.mb.on{border-color:var(--gl);background:rgba(245,158,11,.12)}
.main{flex:1;display:flex;flex-direction:column;min-width:0}
.ch{padding:.9rem 1.4rem;background:var(--bg2);border-bottom:1px solid var(--br);display:flex;align-items:center;gap:.7rem}
.cav{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#155e75,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;position:relative}
.od{position:absolute;bottom:2px;left:2px;width:9px;height:9px;border-radius:50%;background:#10b981;border:2px solid var(--bg2)}
.ci h2{font-size:.92rem;font-weight:700}.ci p{font-size:.72rem;color:#10b981;font-weight:600}
.ha{margin-right:auto}
.hb{background:var(--s2);border:1px solid var(--br);color:var(--t2);width:34px;height:34px;border-radius:7px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.82rem;transition:all .2s}
.hb:hover{border-color:var(--p);color:var(--p)}
.msgs{flex:1;overflow-y:auto;padding:1.25rem;display:flex;flex-direction:column;gap:.9rem;scroll-behavior:smooth;scrollbar-width:thin;scrollbar-color:var(--br) transparent}
.msgs::-webkit-scrollbar{width:4px}.msgs::-webkit-scrollbar-thumb{background:var(--br);border-radius:4px}
.msg{display:flex;gap:.55rem;max-width:76%;align-items:flex-end}
.msg.bot{align-self:flex-start}.msg.user{align-self:flex-end;flex-direction:row-reverse}
.av{width:30px;height:30px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#155e75,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:.82rem}
.msg.user .av{background:linear-gradient(135deg,#4f46e5,#7c3aed)}
.bub{padding:.7rem .95rem;font-size:.88rem;line-height:1.65;font-weight:500;max-width:100%;word-break:break-word;white-space:pre-wrap}
.msg.bot .bub{background:var(--s2);border:1px solid var(--br);border-radius:.3rem 1.05rem 1.05rem 1.05rem;color:var(--t)}
.msg.user .bub{background:linear-gradient(135deg,#0891b2,#06b6d4);color:#fff;border-radius:1.05rem .3rem 1.05rem 1.05rem}
.vc{margin-top:.55rem;background:linear-gradient(135deg,rgba(21,94,117,.3),rgba(6,182,212,.1));border:1px solid rgba(6,182,212,.28);border-radius:.7rem;padding:.7rem .85rem;font-size:.8rem;line-height:1.7}
.vc .vr{color:var(--gl);font-weight:700;font-size:.73rem;display:block;margin-bottom:.22rem}
.vc .vt{color:#cffafe;font-style:italic}
.mt{font-size:.63rem;color:var(--t3);align-self:flex-end;padding:0 .2rem;white-space:nowrap}
.typ{display:flex;align-items:center;gap:5px;padding:.7rem .95rem}
.typ span{width:6px;height:6px;border-radius:50%;background:var(--p);display:inline-block;animation:bop 1.2s infinite}
.typ span:nth-child(2){animation-delay:.2s}.typ span:nth-child(3){animation-delay:.4s}
@keyframes bop{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-5px)}}
.wel{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;gap:1.1rem;padding:2rem;text-align:center}
.wi{font-size:3.2rem;opacity:.9}.wel h2{font-size:1.3rem;font-weight:800}
.wel p{color:var(--t2);font-size:.86rem;max-width:330px;line-height:1.7}
.cps{display:flex;flex-wrap:wrap;gap:.45rem;justify-content:center;margin-top:.4rem}
.cp{background:var(--s2);border:1px solid var(--br);border-radius:2rem;padding:.42rem .85rem;font-size:.78rem;font-weight:600;color:var(--t2);cursor:pointer;transition:all .2s;font-family:'Cairo',sans-serif}
.cp:hover{border-color:var(--p);color:var(--p);background:var(--pg)}
.ia{padding:.9rem 1.4rem 1.1rem;background:var(--bg2);border-top:1px solid var(--br)}
.ir{display:flex;align-items:flex-end;gap:.55rem;background:var(--s2);border:1px solid var(--br);border-radius:.95rem;padding:.45rem .45rem .45rem .95rem;transition:border-color .2s}
.ir:focus-within{border-color:var(--p)}
#inp{flex:1;background:none;border:none;outline:none;color:var(--t);font-family:'Cairo',sans-serif;font-size:.88rem;font-weight:500;resize:none;max-height:110px;min-height:22px;line-height:1.6;direction:rtl}
#inp::placeholder{color:var(--t3)}
#sb{width:36px;height:36px;border-radius:.55rem;background:var(--p);border:none;color:#fff;font-size:.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s,transform .15s;flex-shrink:0}
#sb:hover{background:var(--pd)}#sb:active{transform:scale(.92)}
#sb:disabled{background:var(--bg3);color:var(--t3);cursor:not-allowed}
.hint{font-size:.68rem;color:var(--t3);text-align:center;margin-top:.45rem}
/* debug box */
#dbg{display:none;margin:8px 16px;padding:10px 14px;background:#1a0a0a;border:1px solid #7f1d1d;border-radius:8px;font-size:.75rem;color:#fca5a5;font-family:monospace;direction:ltr;word-break:break-all;max-height:160px;overflow-y:auto}
#dbg.show{display:block}
#tst{position:fixed;bottom:1.4rem;left:50%;transform:translateX(-50%) translateY(70px);background:#1e1e21;border:1px solid #ef4444;color:#fca5a5;padding:.55rem 1.1rem;border-radius:2rem;font-size:.78rem;font-family:'Cairo',sans-serif;opacity:0;transition:all .3s;z-index:9999;pointer-events:none;max-width:90vw;text-align:center}
#tst.on{opacity:1;transform:translateX(-50%) translateY(0)}
@media(max-width:768px){.sidebar{display:none}.msg{max-width:88%}.ch{padding:.75rem 1rem}.msgs{padding:.9rem}.ia{padding:.7rem .9rem .9rem}}
</style>
</head>
<body>
<div id="tst"></div>
<div class="app">
  <aside class="sidebar">
    <div class="logo-area">
      <div class="logo-icon">&#10013;</div>
      <div class="logo-text"><h1>Daily Bible SPS</h1><p>مساعدك الروحي 🤍</p></div>
    </div>
    <div class="div"></div>
    <span class="slbl">اقتراحات سريعة</span>
    <button class="qb" onclick="sq('محتاج آية تشجعني النهارده')"><i class="fa-solid fa-book-open-reader"></i> آية تشجيعية</button>
    <button class="qb" onclick="sq('أنا حاسس بضغط كتير')"><i class="fa-solid fa-heart"></i> أنا مضغوط</button>
    <button class="qb" onclick="sq('عايز أفضل في قراية الإنجيل')"><i class="fa-solid fa-bible"></i> نصيحة للقراية</button>
    <button class="qb" onclick="sq('محتاج تشجيع في حياتي')"><i class="fa-solid fa-star"></i> تشجيعني</button>
    <button class="qb" onclick="sq('أنا زعلان من حاجة')"><i class="fa-solid fa-face-sad-tear"></i> أنا زعلان</button>
    <button class="qb" onclick="sq('عايز أعرف أكتر عن الصلاة')"><i class="fa-solid fa-praying-hands"></i> عن الصلاة</button>
    <div class="div"></div>
    <div class="sbot">
      <div class="mbar">
        <p>كيف حالك؟</p>
        <div class="memojis">
          <button class="mb" onclick="pm(this,'أنا بخير ومبسوط')">😊</button>
          <button class="mb" onclick="pm(this,'أنا تمام بس مش في أحسن أيامي')">😐</button>
          <button class="mb" onclick="pm(this,'أنا حاسس بحزن شوية')">😔</button>
          <button class="mb" onclick="pm(this,'أنا مضغوط جداً')">😰</button>
          <button class="mb" onclick="pm(this,'محتاج دعاء')">🙏</button>
        </div>
      </div>
    </div>
  </aside>

  <main class="main">
    <header class="ch">
      <div class="cav">&#10013;<div class="od"></div></div>
      <div class="ci"><h2>المساعد الروحي</h2><p>متاح دايماً 🤍</p></div>
      <div class="ha"><button class="hb" onclick="clr()" title="محادثة جديدة"><i class="fa-solid fa-rotate-right"></i></button></div>
    </header>
    <div id="dbg"></div>
    <div class="msgs" id="msgs">
      <div class="wel" id="wel">
        <div class="wi">🕊️</div>
        <h2>أهلاً بيك في Daily Bible SPS</h2>
        <p>أنا هنا أساعدك وأشجعك روحياً وعاطفياً. مش لازم تكون لوحدك ✨</p>
        <div class="cps">
          <button class="cp" onclick="sq('أنا مضغوط')">أنا مضغوط</button>
          <button class="cp" onclick="sq('محتاج آية تشجعني')">محتاج آية</button>
          <button class="cp" onclick="sq('أنا زعلان')">أنا زعلان</button>
          <button class="cp" onclick="sq('عايز أتشجع')">شجعني</button>
          <button class="cp" onclick="sq('كلمني عن الصلاة')">عن الصلاة</button>
        </div>
      </div>
    </div>
    <div class="ia">
      <div class="ir">
        <textarea id="inp" rows="1" placeholder="اكتب إيه اللي في بالك…" maxlength="600"></textarea>
        <button id="sb" onclick="hs()"><i class="fa-solid fa-paper-plane"></i></button>
      </div>
      <p class="hint">⚠️ مش بديل لأب اعترافك – للدعم الروحي فقط 🙏 · Powered by Gemini</p>
    </div>
  </main>
</div>

<script>
let conv=[],busy=false;

function toast(m,dur){
  const el=document.getElementById('tst');
  el.textContent=m; el.classList.add('on');
  clearTimeout(el._t);
  el._t=setTimeout(()=>el.classList.remove('on'),dur||5000);
}
function dbg(obj){
  const el=document.getElementById('dbg');
  el.textContent=JSON.stringify(obj,null,2);
  el.classList.add('show');
}
function now(){return new Date().toLocaleTimeString('ar-EG',{hour:'2-digit',minute:'2-digit'})}
function hw(){const w=document.getElementById('wel');if(w)w.remove()}

function addMsg(role,text,verse){
  hw();
  const A=document.getElementById('msgs');
  const d=document.createElement('div'); d.className='msg '+role;
  const av=document.createElement('div'); av.className='av'; av.textContent=role==='bot'?'✝':'👤';
  const inn=document.createElement('div'); inn.style.cssText='display:flex;flex-direction:column;gap:3px;min-width:0';
  const b=document.createElement('div'); b.className='bub'; b.textContent=text; inn.appendChild(b);
  if(verse){
    const v=document.createElement('div'); v.className='vc';
    v.innerHTML='<span class="vr">📖 '+verse.ref+'</span><span class="vt">"'+verse.text+'"</span>';
    inn.appendChild(v);
  }
  const t=document.createElement('div'); t.className='mt'; t.textContent=now(); inn.appendChild(t);
  d.appendChild(av); d.appendChild(inn); A.appendChild(d); A.scrollTop=A.scrollHeight;
}

function showTyp(){
  hw(); const A=document.getElementById('msgs');
  const d=document.createElement('div'); d.className='msg bot'; d.id='typ';
  const av=document.createElement('div'); av.className='av'; av.textContent='✝';
  const b=document.createElement('div'); b.className='bub'; b.style.padding='.5rem .95rem';
  b.innerHTML='<div class="typ"><span></span><span></span><span></span></div>';
  d.appendChild(av); d.appendChild(b); A.appendChild(d); A.scrollTop=A.scrollHeight;
}
function remTyp(){const t=document.getElementById('typ');if(t)t.remove()}

function pv(txt){
  const m=txt.match(/\[VERSE:([^:]+):([^\]]+)\]/);
  if(m)return{clean:txt.replace(/\[VERSE:[^\]]+\]/,'').trim(),verse:{ref:m[1].trim(),text:m[2].trim()}};
  return{clean:txt,verse:null};
}

async function send(msg){
  if(!msg.trim()||busy)return;
  busy=true; document.getElementById('sb').disabled=true;
  addMsg('user',msg);
  conv.push({role:'user',parts:[{text:msg}]});
  showTyp();
  try{
    const res=await fetch('chatbot.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({messages:conv})
    });

    // اقرأ الرد كـ text الأول
    const rawText = await res.text();
    remTyp();

    // حاول تعمله parse
    let data;
    try{
      data = JSON.parse(rawText);
    }catch(e){
      // مش JSON — ظاهر إن السيرفر رجّع HTML أو رسالة خطأ
      dbg({status: res.status, preview: rawText.substring(0,500)});
      toast('⚠️ السيرفر رجّع رد غير متوقع — شوف الـ debug box',6000);
      addMsg('bot','في مشكلة في الاتصال. شوف الـ debug box الأحمر لمعرفة السبب 🙏');
      conv.pop(); return;
    }

    if(data.error){
      dbg(data);
      // وضّح للمستخدم
      if(data.error.includes('all_methods_failed')){
        const msg2 = `السيرفر (InfinityFree) بيحجب الاتصال بـ Gemini 😔\n\ncURL: ${data.curl}\nallow_url_fopen: ${data.allow_url_fopen}\n\nالحل: انتقل لهوستينج تاني زي 000webhost أو Render`;
        addMsg('bot', msg2);
        toast('السيرفر المجاني بيحجب الطلبات الخارجية', 7000);
      } else {
        addMsg('bot','مش قادر أرد دلوقتي 🙏');
        toast('خطأ: '+data.error);
      }
      conv.pop(); return;
    }

    const raw2=data?.candidates?.[0]?.content?.parts?.[0]?.text||'مش قادر أرد دلوقتي 🙏';
    conv.push({role:'model',parts:[{text:raw2}]});
    const{clean,verse}=pv(raw2);
    addMsg('bot',clean,verse);

  }catch(e){
    remTyp();
    dbg({js_error: e.message, stack: e.stack});
    toast('⚠️ JS error: '+e.message);
    addMsg('bot','حصل خطأ غير متوقع 🙏');
    conv.pop();
  }finally{
    busy=false; document.getElementById('sb').disabled=false;
  }
}

function hs(){const i=document.getElementById('inp');const v=i.value.trim();if(!v)return;i.value='';i.style.height='auto';send(v)}
function sq(t){document.getElementById('inp').value=t;hs()}
function pm(btn,msg){document.querySelectorAll('.mb').forEach(b=>b.classList.remove('on'));btn.classList.add('on');send(msg)}
function clr(){
  conv=[]; document.getElementById('msgs').innerHTML='';
  document.getElementById('dbg').classList.remove('show');
  const w=document.createElement('div'); w.className='wel'; w.id='wel';
  w.innerHTML='<div class="wi">🕊️</div><h2>أهلاً بيك في Daily Bible SPS</h2><p>أنا هنا أساعدك وأشجعك روحياً وعاطفياً. مش لازم تكون لوحدك ✨</p><div class="cps"><button class="cp" onclick="sq(\'أنا مضغوط\')">أنا مضغوط</button><button class="cp" onclick="sq(\'محتاج آية تشجعني\')">محتاج آية</button><button class="cp" onclick="sq(\'أنا زعلان\')">أنا زعلان</button><button class="cp" onclick="sq(\'عايز أتشجع\')">شجعني</button><button class="cp" onclick="sq(\'كلمني عن الصلاة\')">عن الصلاة</button></div>';
  document.getElementById('msgs').appendChild(w);
  document.querySelectorAll('.mb').forEach(b=>b.classList.remove('on'));
}
document.getElementById('inp').addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,110)+'px'});
document.getElementById('inp').addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();hs()}});
</script>
</body>
</html>
