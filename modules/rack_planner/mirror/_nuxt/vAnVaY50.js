(function(){try{var t=typeof window<"u"?window:typeof global<"u"?global:typeof globalThis<"u"?globalThis:typeof self<"u"?self:{},e=new t.Error().stack;e&&(t._sentryDebugIds=t._sentryDebugIds||{},t._sentryDebugIds[e]="bcd50686-086d-4908-8e8f-b3bb041ebdbe",t._sentryDebugIdIdentifier="sentry-dbid-bcd50686-086d-4908-8e8f-b3bb041ebdbe")}catch{}})();var fe={};/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const De={NODE_CLIENT:!1,NODE_ADMIN:!1,SDK_VERSION:"${JSCORE_VERSION}"};/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const tt=function(t,e){if(!t)throw rt(e)},rt=function(t){return new Error("Firebase Database ("+De.SDK_VERSION+") INTERNAL ASSERT FAILED: "+t)};/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const ke=function(t){const e=[];let r=0;for(let i=0;i<t.length;i++){let n=t.charCodeAt(i);n<128?e[r++]=n:n<2048?(e[r++]=n>>6|192,e[r++]=n&63|128):(n&64512)===55296&&i+1<t.length&&(t.charCodeAt(i+1)&64512)===56320?(n=65536+((n&1023)<<10)+(t.charCodeAt(++i)&1023),e[r++]=n>>18|240,e[r++]=n>>12&63|128,e[r++]=n>>6&63|128,e[r++]=n&63|128):(e[r++]=n>>12|224,e[r++]=n>>6&63|128,e[r++]=n&63|128)}return e},nt=function(t){const e=[];let r=0,i=0;for(;r<t.length;){const n=t[r++];if(n<128)e[i++]=String.fromCharCode(n);else if(n>191&&n<224){const s=t[r++];e[i++]=String.fromCharCode((n&31)<<6|s&63)}else if(n>239&&n<365){const s=t[r++],o=t[r++],a=t[r++],c=((n&7)<<18|(s&63)<<12|(o&63)<<6|a&63)-65536;e[i++]=String.fromCharCode(55296+(c>>10)),e[i++]=String.fromCharCode(56320+(c&1023))}else{const s=t[r++],o=t[r++];e[i++]=String.fromCharCode((n&15)<<12|(s&63)<<6|o&63)}}return e.join("")},ee={byteToCharMap_:null,charToByteMap_:null,byteToCharMapWebSafe_:null,charToByteMapWebSafe_:null,ENCODED_VALS_BASE:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789",get ENCODED_VALS(){return this.ENCODED_VALS_BASE+"+/="},get ENCODED_VALS_WEBSAFE(){return this.ENCODED_VALS_BASE+"-_."},HAS_NATIVE_SUPPORT:typeof atob=="function",encodeByteArray(t,e){if(!Array.isArray(t))throw Error("encodeByteArray takes an array as a parameter");this.init_();const r=e?this.byteToCharMapWebSafe_:this.byteToCharMap_,i=[];for(let n=0;n<t.length;n+=3){const s=t[n],o=n+1<t.length,a=o?t[n+1]:0,c=n+2<t.length,h=c?t[n+2]:0,p=s>>2,l=(s&3)<<4|a>>4;let g=(a&15)<<2|h>>6,k=h&63;c||(k=64,o||(g=64)),i.push(r[p],r[l],r[g],r[k])}return i.join("")},encodeString(t,e){return this.HAS_NATIVE_SUPPORT&&!e?btoa(t):this.encodeByteArray(ke(t),e)},decodeString(t,e){return this.HAS_NATIVE_SUPPORT&&!e?atob(t):nt(this.decodeStringToByteArray(t,e))},decodeStringToByteArray(t,e){this.init_();const r=e?this.charToByteMapWebSafe_:this.charToByteMap_,i=[];for(let n=0;n<t.length;){const s=r[t.charAt(n++)],a=n<t.length?r[t.charAt(n)]:0;++n;const h=n<t.length?r[t.charAt(n)]:64;++n;const l=n<t.length?r[t.charAt(n)]:64;if(++n,s==null||a==null||h==null||l==null)throw new it;const g=s<<2|a>>4;if(i.push(g),h!==64){const k=a<<4&240|h>>2;if(i.push(k),l!==64){const et=h<<6&192|l;i.push(et)}}}return i},init_(){if(!this.byteToCharMap_){this.byteToCharMap_={},this.charToByteMap_={},this.byteToCharMapWebSafe_={},this.charToByteMapWebSafe_={};for(let t=0;t<this.ENCODED_VALS.length;t++)this.byteToCharMap_[t]=this.ENCODED_VALS.charAt(t),this.charToByteMap_[this.byteToCharMap_[t]]=t,this.byteToCharMapWebSafe_[t]=this.ENCODED_VALS_WEBSAFE.charAt(t),this.charToByteMapWebSafe_[this.byteToCharMapWebSafe_[t]]=t,t>=this.ENCODED_VALS_BASE.length&&(this.charToByteMap_[this.ENCODED_VALS_WEBSAFE.charAt(t)]=t,this.charToByteMapWebSafe_[this.ENCODED_VALS.charAt(t)]=t)}}};class it extends Error{constructor(){super(...arguments),this.name="DecodeBase64StringError"}}const st=function(t){const e=ke(t);return ee.encodeByteArray(e,!0)},P=function(t){return st(t).replace(/\./g,"")},W=function(t){try{return ee.decodeString(t,!0)}catch(e){console.error("base64Decode failed: ",e)}return null};/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function An(t){return Oe(void 0,t)}function Oe(t,e){if(!(e instanceof Object))return e;switch(e.constructor){case Date:const r=e;return new Date(r.getTime());case Object:t===void 0&&(t={});break;case Array:t=[];break;default:return e}for(const r in e)!e.hasOwnProperty(r)||!ot(r)||(t[r]=Oe(t[r],e[r]));return t}function ot(t){return t!=="__proto__"}/**
 * @license
 * Copyright 2022 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Re(){if(typeof self<"u")return self;if(typeof window<"u")return window;if(typeof global<"u")return global;throw new Error("Unable to locate global object.")}/**
 * @license
 * Copyright 2022 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const at=()=>Re().__FIREBASE_DEFAULTS__,ct=()=>{if(typeof process>"u"||typeof fe>"u")return;const t=fe.__FIREBASE_DEFAULTS__;if(t)return JSON.parse(t)},lt=()=>{if(typeof document>"u")return;let t;try{t=document.cookie.match(/__FIREBASE_DEFAULTS__=([^;]+)/)}catch{return}const e=t&&W(t[1]);return e&&JSON.parse(e)},x=()=>{try{return at()||ct()||lt()}catch(t){console.info(`Unable to get __FIREBASE_DEFAULTS__ due to: ${t}`);return}},ht=t=>{var e,r;return(r=(e=x())===null||e===void 0?void 0:e.emulatorHosts)===null||r===void 0?void 0:r[t]},Tn=t=>{const e=ht(t);if(!e)return;const r=e.lastIndexOf(":");if(r<=0||r+1===e.length)throw new Error(`Invalid host ${e} with no separate hostname and port!`);const i=parseInt(e.substring(r+1),10);return e[0]==="["?[e.substring(1,r-1),i]:[e.substring(0,r),i]},Pe=()=>{var t;return(t=x())===null||t===void 0?void 0:t.config},In=t=>{var e;return(e=x())===null||e===void 0?void 0:e[`_${t}`]};/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class w{constructor(){this.reject=()=>{},this.resolve=()=>{},this.promise=new Promise((e,r)=>{this.resolve=e,this.reject=r})}wrapCallback(e){return(r,i)=>{r?this.reject(r):this.resolve(i),typeof e=="function"&&(this.promise.catch(()=>{}),e.length===1?e(r):e(r,i))}}}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Sn(t,e){if(t.uid)throw new Error('The "uid" field is no longer supported by mockUserToken. Please use "sub" instead for Firebase Auth User ID.');const r={alg:"none",type:"JWT"},i=e||"demo-project",n=t.iat||0,s=t.sub||t.user_id;if(!s)throw new Error("mockUserToken must contain 'sub' or 'user_id' field!");const o=Object.assign({iss:`https://securetoken.google.com/${i}`,aud:i,iat:n,exp:n+3600,auth_time:n,sub:s,user_id:s,firebase:{sign_in_provider:"custom",identities:{}}},t);return[P(JSON.stringify(r)),P(JSON.stringify(o)),""].join(".")}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Be(){return typeof navigator<"u"&&typeof navigator.userAgent=="string"?navigator.userAgent:""}function Cn(){return typeof window<"u"&&!!(window.cordova||window.phonegap||window.PhoneGap)&&/ios|iphone|ipod|ipad|android|blackberry|iemobile/i.test(Be())}function dt(){var t;const e=(t=x())===null||t===void 0?void 0:t.forceEnvironment;if(e==="node")return!0;if(e==="browser")return!1;try{return Object.prototype.toString.call(globalThis.process)==="[object process]"}catch{return!1}}function Dn(){return typeof navigator<"u"&&navigator.userAgent==="Cloudflare-Workers"}function kn(){const t=typeof chrome=="object"?chrome.runtime:typeof browser=="object"?browser.runtime:void 0;return typeof t=="object"&&t.id!==void 0}function On(){return typeof navigator=="object"&&navigator.product==="ReactNative"}function Rn(){const t=Be();return t.indexOf("MSIE ")>=0||t.indexOf("Trident/")>=0}function Pn(){return De.NODE_ADMIN===!0}function Bn(){return!dt()&&!!navigator.userAgent&&navigator.userAgent.includes("Safari")&&!navigator.userAgent.includes("Chrome")}function te(){try{return typeof indexedDB=="object"}catch{return!1}}function ut(){return new Promise((t,e)=>{try{let r=!0;const i="validate-browser-context-for-indexeddb-analytics-module",n=self.indexedDB.open(i);n.onsuccess=()=>{n.result.close(),r||self.indexedDB.deleteDatabase(i),t(!0)},n.onupgradeneeded=()=>{r=!1},n.onerror=()=>{var s;e(((s=n.error)===null||s===void 0?void 0:s.message)||"")}}catch(r){e(r)}})}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const ft="FirebaseError";class D extends Error{constructor(e,r,i){super(r),this.code=e,this.customData=i,this.name=ft,Object.setPrototypeOf(this,D.prototype),Error.captureStackTrace&&Error.captureStackTrace(this,re.prototype.create)}}class re{constructor(e,r,i){this.service=e,this.serviceName=r,this.errors=i}create(e,...r){const i=r[0]||{},n=`${this.service}/${e}`,s=this.errors[e],o=s?pt(s,i):"Error",a=`${this.serviceName}: ${o} (${n}).`;return new D(n,a,i)}}function pt(t,e){return t.replace(gt,(r,i)=>{const n=e[i];return n!=null?String(n):`<${i}?>`})}const gt=/\{\$([^}]+)}/g;/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function pe(t){return JSON.parse(t)}function Nn(t){return JSON.stringify(t)}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const ne=function(t){let e={},r={},i={},n="";try{const s=t.split(".");e=pe(W(s[0])||""),r=pe(W(s[1])||""),n=s[2],i=r.d||{},delete r.d}catch{}return{header:e,claims:r,data:i,signature:n}},mt=function(t){const e=ne(t).claims;return typeof e=="object"&&e.hasOwnProperty("iat")?e.iat:null},Mn=function(t){const e=ne(t),r=e.claims;return!!r&&typeof r=="object"&&r.hasOwnProperty("iat")},xn=function(t){const e=ne(t).claims;return typeof e=="object"&&e.admin===!0};/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function $n(t,e){return Object.prototype.hasOwnProperty.call(t,e)}function Hn(t,e){if(Object.prototype.hasOwnProperty.call(t,e))return t[e]}function Ln(t){for(const e in t)if(Object.prototype.hasOwnProperty.call(t,e))return!1;return!0}function Fn(t,e,r){const i={};for(const n in t)Object.prototype.hasOwnProperty.call(t,n)&&(i[n]=e.call(r,t[n],n,t));return i}function K(t,e){if(t===e)return!0;const r=Object.keys(t),i=Object.keys(e);for(const n of r){if(!i.includes(n))return!1;const s=t[n],o=e[n];if(ge(s)&&ge(o)){if(!K(s,o))return!1}else if(s!==o)return!1}for(const n of i)if(!r.includes(n))return!1;return!0}function ge(t){return t!==null&&typeof t=="object"}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function jn(t){const e=[];for(const[r,i]of Object.entries(t))Array.isArray(i)?i.forEach(n=>{e.push(encodeURIComponent(r)+"="+encodeURIComponent(n))}):e.push(encodeURIComponent(r)+"="+encodeURIComponent(i));return e.length?"&"+e.join("&"):""}function zn(t){const e={};return t.replace(/^\?/,"").split("&").forEach(i=>{if(i){const[n,s]=i.split("=");e[decodeURIComponent(n)]=decodeURIComponent(s)}}),e}function Un(t){const e=t.indexOf("?");if(!e)return"";const r=t.indexOf("#",e);return t.substring(e,r>0?r:void 0)}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Vn{constructor(){this.chain_=[],this.buf_=[],this.W_=[],this.pad_=[],this.inbuf_=0,this.total_=0,this.blockSize=512/8,this.pad_[0]=128;for(let e=1;e<this.blockSize;++e)this.pad_[e]=0;this.reset()}reset(){this.chain_[0]=1732584193,this.chain_[1]=4023233417,this.chain_[2]=2562383102,this.chain_[3]=271733878,this.chain_[4]=3285377520,this.inbuf_=0,this.total_=0}compress_(e,r){r||(r=0);const i=this.W_;if(typeof e=="string")for(let l=0;l<16;l++)i[l]=e.charCodeAt(r)<<24|e.charCodeAt(r+1)<<16|e.charCodeAt(r+2)<<8|e.charCodeAt(r+3),r+=4;else for(let l=0;l<16;l++)i[l]=e[r]<<24|e[r+1]<<16|e[r+2]<<8|e[r+3],r+=4;for(let l=16;l<80;l++){const g=i[l-3]^i[l-8]^i[l-14]^i[l-16];i[l]=(g<<1|g>>>31)&4294967295}let n=this.chain_[0],s=this.chain_[1],o=this.chain_[2],a=this.chain_[3],c=this.chain_[4],h,p;for(let l=0;l<80;l++){l<40?l<20?(h=a^s&(o^a),p=1518500249):(h=s^o^a,p=1859775393):l<60?(h=s&o|a&(s|o),p=2400959708):(h=s^o^a,p=3395469782);const g=(n<<5|n>>>27)+h+c+p+i[l]&4294967295;c=a,a=o,o=(s<<30|s>>>2)&4294967295,s=n,n=g}this.chain_[0]=this.chain_[0]+n&4294967295,this.chain_[1]=this.chain_[1]+s&4294967295,this.chain_[2]=this.chain_[2]+o&4294967295,this.chain_[3]=this.chain_[3]+a&4294967295,this.chain_[4]=this.chain_[4]+c&4294967295}update(e,r){if(e==null)return;r===void 0&&(r=e.length);const i=r-this.blockSize;let n=0;const s=this.buf_;let o=this.inbuf_;for(;n<r;){if(o===0)for(;n<=i;)this.compress_(e,n),n+=this.blockSize;if(typeof e=="string"){for(;n<r;)if(s[o]=e.charCodeAt(n),++o,++n,o===this.blockSize){this.compress_(s),o=0;break}}else for(;n<r;)if(s[o]=e[n],++o,++n,o===this.blockSize){this.compress_(s),o=0;break}}this.inbuf_=o,this.total_+=r}digest(){const e=[];let r=this.total_*8;this.inbuf_<56?this.update(this.pad_,56-this.inbuf_):this.update(this.pad_,this.blockSize-(this.inbuf_-56));for(let n=this.blockSize-1;n>=56;n--)this.buf_[n]=r&255,r/=256;this.compress_(this.buf_);let i=0;for(let n=0;n<5;n++)for(let s=24;s>=0;s-=8)e[i]=this.chain_[n]>>s&255,++i;return e}}function Wn(t,e){const r=new bt(t,e);return r.subscribe.bind(r)}class bt{constructor(e,r){this.observers=[],this.unsubscribes=[],this.observerCount=0,this.task=Promise.resolve(),this.finalized=!1,this.onNoObservers=r,this.task.then(()=>{e(this)}).catch(i=>{this.error(i)})}next(e){this.forEachObserver(r=>{r.next(e)})}error(e){this.forEachObserver(r=>{r.error(e)}),this.close(e)}complete(){this.forEachObserver(e=>{e.complete()}),this.close()}subscribe(e,r,i){let n;if(e===void 0&&r===void 0&&i===void 0)throw new Error("Missing Observer.");_t(e,["next","error","complete"])?n=e:n={next:e,error:r,complete:i},n.next===void 0&&(n.next=L),n.error===void 0&&(n.error=L),n.complete===void 0&&(n.complete=L);const s=this.unsubscribeOne.bind(this,this.observers.length);return this.finalized&&this.task.then(()=>{try{this.finalError?n.error(this.finalError):n.complete()}catch{}}),this.observers.push(n),s}unsubscribeOne(e){this.observers===void 0||this.observers[e]===void 0||(delete this.observers[e],this.observerCount-=1,this.observerCount===0&&this.onNoObservers!==void 0&&this.onNoObservers(this))}forEachObserver(e){if(!this.finalized)for(let r=0;r<this.observers.length;r++)this.sendOne(r,e)}sendOne(e,r){this.task.then(()=>{if(this.observers!==void 0&&this.observers[e]!==void 0)try{r(this.observers[e])}catch(i){typeof console<"u"&&console.error&&console.error(i)}})}close(e){this.finalized||(this.finalized=!0,e!==void 0&&(this.finalError=e),this.task.then(()=>{this.observers=void 0,this.onNoObservers=void 0}))}}function _t(t,e){if(typeof t!="object"||t===null)return!1;for(const r of e)if(r in t&&typeof t[r]=="function")return!0;return!1}function L(){}function Kn(t,e){return`${t} failed: ${e} argument `}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Gn=function(t){const e=[];let r=0;for(let i=0;i<t.length;i++){let n=t.charCodeAt(i);if(n>=55296&&n<=56319){const s=n-55296;i++,tt(i<t.length,"Surrogate pair missing trail surrogate.");const o=t.charCodeAt(i)-56320;n=65536+(s<<10)+o}n<128?e[r++]=n:n<2048?(e[r++]=n>>6|192,e[r++]=n&63|128):n<65536?(e[r++]=n>>12|224,e[r++]=n>>6&63|128,e[r++]=n&63|128):(e[r++]=n>>18|240,e[r++]=n>>12&63|128,e[r++]=n>>6&63|128,e[r++]=n&63|128)}return e},qn=function(t){let e=0;for(let r=0;r<t.length;r++){const i=t.charCodeAt(r);i<128?e++:i<2048?e+=2:i>=55296&&i<=56319?(e+=4,r++):e+=3}return e};/**
 * @license
 * Copyright 2022 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Et=function(){return"xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g,t=>{const e=Math.random()*16|0;return(t==="x"?e:e&3|8).toString(16)})};/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const vt=1e3,wt=2,yt=4*60*60*1e3,At=.5;function Tt(t,e=vt,r=wt){const i=e*Math.pow(r,t),n=Math.round(At*i*(Math.random()-.5)*2);return Math.min(yt,i+n)}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function It(t){return t&&t._delegate?t._delegate:t}class y{constructor(e,r,i){this.name=e,this.instanceFactory=r,this.type=i,this.multipleInstances=!1,this.serviceProps={},this.instantiationMode="LAZY",this.onInstanceCreated=null}setInstantiationMode(e){return this.instantiationMode=e,this}setMultipleInstances(e){return this.multipleInstances=e,this}setServiceProps(e){return this.serviceProps=e,this}setInstanceCreatedCallback(e){return this.onInstanceCreated=e,this}}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const E="[DEFAULT]";/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class St{constructor(e,r){this.name=e,this.container=r,this.component=null,this.instances=new Map,this.instancesDeferred=new Map,this.instancesOptions=new Map,this.onInitCallbacks=new Map}get(e){const r=this.normalizeInstanceIdentifier(e);if(!this.instancesDeferred.has(r)){const i=new w;if(this.instancesDeferred.set(r,i),this.isInitialized(r)||this.shouldAutoInitialize())try{const n=this.getOrInitializeService({instanceIdentifier:r});n&&i.resolve(n)}catch{}}return this.instancesDeferred.get(r).promise}getImmediate(e){var r;const i=this.normalizeInstanceIdentifier(e==null?void 0:e.identifier),n=(r=e==null?void 0:e.optional)!==null&&r!==void 0?r:!1;if(this.isInitialized(i)||this.shouldAutoInitialize())try{return this.getOrInitializeService({instanceIdentifier:i})}catch(s){if(n)return null;throw s}else{if(n)return null;throw Error(`Service ${this.name} is not available`)}}getComponent(){return this.component}setComponent(e){if(e.name!==this.name)throw Error(`Mismatching Component ${e.name} for Provider ${this.name}.`);if(this.component)throw Error(`Component for ${this.name} has already been provided`);if(this.component=e,!!this.shouldAutoInitialize()){if(Dt(e))try{this.getOrInitializeService({instanceIdentifier:E})}catch{}for(const[r,i]of this.instancesDeferred.entries()){const n=this.normalizeInstanceIdentifier(r);try{const s=this.getOrInitializeService({instanceIdentifier:n});i.resolve(s)}catch{}}}}clearInstance(e=E){this.instancesDeferred.delete(e),this.instancesOptions.delete(e),this.instances.delete(e)}async delete(){const e=Array.from(this.instances.values());await Promise.all([...e.filter(r=>"INTERNAL"in r).map(r=>r.INTERNAL.delete()),...e.filter(r=>"_delete"in r).map(r=>r._delete())])}isComponentSet(){return this.component!=null}isInitialized(e=E){return this.instances.has(e)}getOptions(e=E){return this.instancesOptions.get(e)||{}}initialize(e={}){const{options:r={}}=e,i=this.normalizeInstanceIdentifier(e.instanceIdentifier);if(this.isInitialized(i))throw Error(`${this.name}(${i}) has already been initialized`);if(!this.isComponentSet())throw Error(`Component ${this.name} has not been registered yet`);const n=this.getOrInitializeService({instanceIdentifier:i,options:r});for(const[s,o]of this.instancesDeferred.entries()){const a=this.normalizeInstanceIdentifier(s);i===a&&o.resolve(n)}return n}onInit(e,r){var i;const n=this.normalizeInstanceIdentifier(r),s=(i=this.onInitCallbacks.get(n))!==null&&i!==void 0?i:new Set;s.add(e),this.onInitCallbacks.set(n,s);const o=this.instances.get(n);return o&&e(o,n),()=>{s.delete(e)}}invokeOnInitCallbacks(e,r){const i=this.onInitCallbacks.get(r);if(i)for(const n of i)try{n(e,r)}catch{}}getOrInitializeService({instanceIdentifier:e,options:r={}}){let i=this.instances.get(e);if(!i&&this.component&&(i=this.component.instanceFactory(this.container,{instanceIdentifier:Ct(e),options:r}),this.instances.set(e,i),this.instancesOptions.set(e,r),this.invokeOnInitCallbacks(i,e),this.component.onInstanceCreated))try{this.component.onInstanceCreated(this.container,e,i)}catch{}return i||null}normalizeInstanceIdentifier(e=E){return this.component?this.component.multipleInstances?e:E:e}shouldAutoInitialize(){return!!this.component&&this.component.instantiationMode!=="EXPLICIT"}}function Ct(t){return t===E?void 0:t}function Dt(t){return t.instantiationMode==="EAGER"}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class kt{constructor(e){this.name=e,this.providers=new Map}addComponent(e){const r=this.getProvider(e.name);if(r.isComponentSet())throw new Error(`Component ${e.name} has already been registered with ${this.name}`);r.setComponent(e)}addOrOverwriteComponent(e){this.getProvider(e.name).isComponentSet()&&this.providers.delete(e.name),this.addComponent(e)}getProvider(e){if(this.providers.has(e))return this.providers.get(e);const r=new St(e,this);return this.providers.set(e,r),r}getProviders(){return Array.from(this.providers.values())}}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */var d;(function(t){t[t.DEBUG=0]="DEBUG",t[t.VERBOSE=1]="VERBOSE",t[t.INFO=2]="INFO",t[t.WARN=3]="WARN",t[t.ERROR=4]="ERROR",t[t.SILENT=5]="SILENT"})(d||(d={}));const Ot={debug:d.DEBUG,verbose:d.VERBOSE,info:d.INFO,warn:d.WARN,error:d.ERROR,silent:d.SILENT},Rt=d.INFO,Pt={[d.DEBUG]:"log",[d.VERBOSE]:"log",[d.INFO]:"info",[d.WARN]:"warn",[d.ERROR]:"error"},Bt=(t,e,...r)=>{if(e<t.logLevel)return;const i=new Date().toISOString(),n=Pt[e];if(n)console[n](`[${i}]  ${t.name}:`,...r);else throw new Error(`Attempted to log a message with an invalid logType (value: ${e})`)};class Ne{constructor(e){this.name=e,this._logLevel=Rt,this._logHandler=Bt,this._userLogHandler=null}get logLevel(){return this._logLevel}set logLevel(e){if(!(e in d))throw new TypeError(`Invalid value "${e}" assigned to \`logLevel\``);this._logLevel=e}setLogLevel(e){this._logLevel=typeof e=="string"?Ot[e]:e}get logHandler(){return this._logHandler}set logHandler(e){if(typeof e!="function")throw new TypeError("Value assigned to `logHandler` must be a function");this._logHandler=e}get userLogHandler(){return this._userLogHandler}set userLogHandler(e){this._userLogHandler=e}debug(...e){this._userLogHandler&&this._userLogHandler(this,d.DEBUG,...e),this._logHandler(this,d.DEBUG,...e)}log(...e){this._userLogHandler&&this._userLogHandler(this,d.VERBOSE,...e),this._logHandler(this,d.VERBOSE,...e)}info(...e){this._userLogHandler&&this._userLogHandler(this,d.INFO,...e),this._logHandler(this,d.INFO,...e)}warn(...e){this._userLogHandler&&this._userLogHandler(this,d.WARN,...e),this._logHandler(this,d.WARN,...e)}error(...e){this._userLogHandler&&this._userLogHandler(this,d.ERROR,...e),this._logHandler(this,d.ERROR,...e)}}const Nt=(t,e)=>e.some(r=>t instanceof r);let me,be;function Mt(){return me||(me=[IDBDatabase,IDBObjectStore,IDBIndex,IDBCursor,IDBTransaction])}function xt(){return be||(be=[IDBCursor.prototype.advance,IDBCursor.prototype.continue,IDBCursor.prototype.continuePrimaryKey])}const Me=new WeakMap,G=new WeakMap,xe=new WeakMap,F=new WeakMap,ie=new WeakMap;function $t(t){const e=new Promise((r,i)=>{const n=()=>{t.removeEventListener("success",s),t.removeEventListener("error",o)},s=()=>{r(b(t.result)),n()},o=()=>{i(t.error),n()};t.addEventListener("success",s),t.addEventListener("error",o)});return e.then(r=>{r instanceof IDBCursor&&Me.set(r,t)}).catch(()=>{}),ie.set(e,t),e}function Ht(t){if(G.has(t))return;const e=new Promise((r,i)=>{const n=()=>{t.removeEventListener("complete",s),t.removeEventListener("error",o),t.removeEventListener("abort",o)},s=()=>{r(),n()},o=()=>{i(t.error||new DOMException("AbortError","AbortError")),n()};t.addEventListener("complete",s),t.addEventListener("error",o),t.addEventListener("abort",o)});G.set(t,e)}let q={get(t,e,r){if(t instanceof IDBTransaction){if(e==="done")return G.get(t);if(e==="objectStoreNames")return t.objectStoreNames||xe.get(t);if(e==="store")return r.objectStoreNames[1]?void 0:r.objectStore(r.objectStoreNames[0])}return b(t[e])},set(t,e,r){return t[e]=r,!0},has(t,e){return t instanceof IDBTransaction&&(e==="done"||e==="store")?!0:e in t}};function Lt(t){q=t(q)}function Ft(t){return t===IDBDatabase.prototype.transaction&&!("objectStoreNames"in IDBTransaction.prototype)?function(e,...r){const i=t.call(j(this),e,...r);return xe.set(i,e.sort?e.sort():[e]),b(i)}:xt().includes(t)?function(...e){return t.apply(j(this),e),b(Me.get(this))}:function(...e){return b(t.apply(j(this),e))}}function jt(t){return typeof t=="function"?Ft(t):(t instanceof IDBTransaction&&Ht(t),Nt(t,Mt())?new Proxy(t,q):t)}function b(t){if(t instanceof IDBRequest)return $t(t);if(F.has(t))return F.get(t);const e=jt(t);return e!==t&&(F.set(t,e),ie.set(e,t)),e}const j=t=>ie.get(t);function zt(t,e,{blocked:r,upgrade:i,blocking:n,terminated:s}={}){const o=indexedDB.open(t,e),a=b(o);return i&&o.addEventListener("upgradeneeded",c=>{i(b(o.result),c.oldVersion,c.newVersion,b(o.transaction),c)}),r&&o.addEventListener("blocked",c=>r(c.oldVersion,c.newVersion,c)),a.then(c=>{s&&c.addEventListener("close",()=>s()),n&&c.addEventListener("versionchange",h=>n(h.oldVersion,h.newVersion,h))}).catch(()=>{}),a}const Ut=["get","getKey","getAll","getAllKeys","count"],Vt=["put","add","delete","clear"],z=new Map;function _e(t,e){if(!(t instanceof IDBDatabase&&!(e in t)&&typeof e=="string"))return;if(z.get(e))return z.get(e);const r=e.replace(/FromIndex$/,""),i=e!==r,n=Vt.includes(r);if(!(r in(i?IDBIndex:IDBObjectStore).prototype)||!(n||Ut.includes(r)))return;const s=async function(o,...a){const c=this.transaction(o,n?"readwrite":"readonly");let h=c.store;return i&&(h=h.index(a.shift())),(await Promise.all([h[r](...a),n&&c.done]))[0]};return z.set(e,s),s}Lt(t=>({...t,get:(e,r,i)=>_e(e,r)||t.get(e,r,i),has:(e,r)=>!!_e(e,r)||t.has(e,r)}));/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Wt{constructor(e){this.container=e}getPlatformInfoString(){return this.container.getProviders().map(r=>{if(Kt(r)){const i=r.getImmediate();return`${i.library}/${i.version}`}else return null}).filter(r=>r).join(" ")}}function Kt(t){const e=t.getComponent();return(e==null?void 0:e.type)==="VERSION"}const J="@firebase/app",Ee="0.10.13";/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const m=new Ne("@firebase/app"),Gt="@firebase/app-compat",qt="@firebase/analytics-compat",Jt="@firebase/analytics",Xt="@firebase/app-check-compat",Yt="@firebase/app-check",Qt="@firebase/auth",Zt="@firebase/auth-compat",er="@firebase/database",tr="@firebase/data-connect",rr="@firebase/database-compat",nr="@firebase/functions",ir="@firebase/functions-compat",sr="@firebase/installations",or="@firebase/installations-compat",ar="@firebase/messaging",cr="@firebase/messaging-compat",lr="@firebase/performance",hr="@firebase/performance-compat",dr="@firebase/remote-config",ur="@firebase/remote-config-compat",fr="@firebase/storage",pr="@firebase/storage-compat",gr="@firebase/firestore",mr="@firebase/vertexai-preview",br="@firebase/firestore-compat",_r="firebase",Er="10.14.1";/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const X="[DEFAULT]",vr={[J]:"fire-core",[Gt]:"fire-core-compat",[Jt]:"fire-analytics",[qt]:"fire-analytics-compat",[Yt]:"fire-app-check",[Xt]:"fire-app-check-compat",[Qt]:"fire-auth",[Zt]:"fire-auth-compat",[er]:"fire-rtdb",[tr]:"fire-data-connect",[rr]:"fire-rtdb-compat",[nr]:"fire-fn",[ir]:"fire-fn-compat",[sr]:"fire-iid",[or]:"fire-iid-compat",[ar]:"fire-fcm",[cr]:"fire-fcm-compat",[lr]:"fire-perf",[hr]:"fire-perf-compat",[dr]:"fire-rc",[ur]:"fire-rc-compat",[fr]:"fire-gcs",[pr]:"fire-gcs-compat",[gr]:"fire-fst",[br]:"fire-fst-compat",[mr]:"fire-vertex","fire-js":"fire-js",[_r]:"fire-js-all"};/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const B=new Map,wr=new Map,Y=new Map;function ve(t,e){try{t.container.addComponent(e)}catch(r){m.debug(`Component ${e.name} failed to register with FirebaseApp ${t.name}`,r)}}function T(t){const e=t.name;if(Y.has(e))return m.debug(`There were multiple attempts to register component ${e}.`),!1;Y.set(e,t);for(const r of B.values())ve(r,t);for(const r of wr.values())ve(r,t);return!0}function se(t,e){const r=t.container.getProvider("heartbeat").getImmediate({optional:!0});return r&&r.triggerHeartbeat(),t.container.getProvider(e)}function Jn(t){return t.settings!==void 0}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const yr={"no-app":"No Firebase App '{$appName}' has been created - call initializeApp() first","bad-app-name":"Illegal App name: '{$appName}'","duplicate-app":"Firebase App named '{$appName}' already exists with different options or config","app-deleted":"Firebase App named '{$appName}' already deleted","server-app-deleted":"Firebase Server App has been deleted","no-options":"Need to provide options, when not being deployed to hosting via source.","invalid-app-argument":"firebase.{$appName}() takes either no argument or a Firebase App instance.","invalid-log-argument":"First argument to `onLog` must be null or a function.","idb-open":"Error thrown when opening IndexedDB. Original error: {$originalErrorMessage}.","idb-get":"Error thrown when reading from IndexedDB. Original error: {$originalErrorMessage}.","idb-set":"Error thrown when writing to IndexedDB. Original error: {$originalErrorMessage}.","idb-delete":"Error thrown when deleting from IndexedDB. Original error: {$originalErrorMessage}.","finalization-registry-not-supported":"FirebaseServerApp deleteOnDeref field defined but the JS runtime does not support FinalizationRegistry.","invalid-server-app-environment":"FirebaseServerApp is not for use in browser environments."},_=new re("app","Firebase",yr);/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Ar{constructor(e,r,i){this._isDeleted=!1,this._options=Object.assign({},e),this._config=Object.assign({},r),this._name=r.name,this._automaticDataCollectionEnabled=r.automaticDataCollectionEnabled,this._container=i,this.container.addComponent(new y("app",()=>this,"PUBLIC"))}get automaticDataCollectionEnabled(){return this.checkDestroyed(),this._automaticDataCollectionEnabled}set automaticDataCollectionEnabled(e){this.checkDestroyed(),this._automaticDataCollectionEnabled=e}get name(){return this.checkDestroyed(),this._name}get options(){return this.checkDestroyed(),this._options}get config(){return this.checkDestroyed(),this._config}get container(){return this._container}get isDeleted(){return this._isDeleted}set isDeleted(e){this._isDeleted=e}checkDestroyed(){if(this.isDeleted)throw _.create("app-deleted",{appName:this._name})}}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Xn=Er;function Tr(t,e={}){let r=t;typeof e!="object"&&(e={name:e});const i=Object.assign({name:X,automaticDataCollectionEnabled:!1},e),n=i.name;if(typeof n!="string"||!n)throw _.create("bad-app-name",{appName:String(n)});if(r||(r=Pe()),!r)throw _.create("no-options");const s=B.get(n);if(s){if(K(r,s.options)&&K(i,s.config))return s;throw _.create("duplicate-app",{appName:n})}const o=new kt(n);for(const c of Y.values())o.addComponent(c);const a=new Ar(r,i,o);return B.set(n,a),a}function Ir(t=X){const e=B.get(t);if(!e&&t===X&&Pe())return Tr();if(!e)throw _.create("no-app",{appName:t});return e}function A(t,e,r){var i;let n=(i=vr[t])!==null&&i!==void 0?i:t;r&&(n+=`-${r}`);const s=n.match(/\s|\//),o=e.match(/\s|\//);if(s||o){const a=[`Unable to register library "${n}" with version "${e}":`];s&&a.push(`library name "${n}" contains illegal characters (whitespace or "/")`),s&&o&&a.push("and"),o&&a.push(`version name "${e}" contains illegal characters (whitespace or "/")`),m.warn(a.join(" "));return}T(new y(`${n}-version`,()=>({library:n,version:e}),"VERSION"))}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Sr="firebase-heartbeat-database",Cr=1,I="firebase-heartbeat-store";let U=null;function $e(){return U||(U=zt(Sr,Cr,{upgrade:(t,e)=>{switch(e){case 0:try{t.createObjectStore(I)}catch(r){console.warn(r)}}}}).catch(t=>{throw _.create("idb-open",{originalErrorMessage:t.message})})),U}async function Dr(t){try{const r=(await $e()).transaction(I),i=await r.objectStore(I).get(He(t));return await r.done,i}catch(e){if(e instanceof D)m.warn(e.message);else{const r=_.create("idb-get",{originalErrorMessage:e==null?void 0:e.message});m.warn(r.message)}}}async function we(t,e){try{const i=(await $e()).transaction(I,"readwrite");await i.objectStore(I).put(e,He(t)),await i.done}catch(r){if(r instanceof D)m.warn(r.message);else{const i=_.create("idb-set",{originalErrorMessage:r==null?void 0:r.message});m.warn(i.message)}}}function He(t){return`${t.name}!${t.options.appId}`}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const kr=1024,Or=30*24*60*60*1e3;class Rr{constructor(e){this.container=e,this._heartbeatsCache=null;const r=this.container.getProvider("app").getImmediate();this._storage=new Br(r),this._heartbeatsCachePromise=this._storage.read().then(i=>(this._heartbeatsCache=i,i))}async triggerHeartbeat(){var e,r;try{const n=this.container.getProvider("platform-logger").getImmediate().getPlatformInfoString(),s=ye();return((e=this._heartbeatsCache)===null||e===void 0?void 0:e.heartbeats)==null&&(this._heartbeatsCache=await this._heartbeatsCachePromise,((r=this._heartbeatsCache)===null||r===void 0?void 0:r.heartbeats)==null)||this._heartbeatsCache.lastSentHeartbeatDate===s||this._heartbeatsCache.heartbeats.some(o=>o.date===s)?void 0:(this._heartbeatsCache.heartbeats.push({date:s,agent:n}),this._heartbeatsCache.heartbeats=this._heartbeatsCache.heartbeats.filter(o=>{const a=new Date(o.date).valueOf();return Date.now()-a<=Or}),this._storage.overwrite(this._heartbeatsCache))}catch(i){m.warn(i)}}async getHeartbeatsHeader(){var e;try{if(this._heartbeatsCache===null&&await this._heartbeatsCachePromise,((e=this._heartbeatsCache)===null||e===void 0?void 0:e.heartbeats)==null||this._heartbeatsCache.heartbeats.length===0)return"";const r=ye(),{heartbeatsToSend:i,unsentEntries:n}=Pr(this._heartbeatsCache.heartbeats),s=P(JSON.stringify({version:2,heartbeats:i}));return this._heartbeatsCache.lastSentHeartbeatDate=r,n.length>0?(this._heartbeatsCache.heartbeats=n,await this._storage.overwrite(this._heartbeatsCache)):(this._heartbeatsCache.heartbeats=[],this._storage.overwrite(this._heartbeatsCache)),s}catch(r){return m.warn(r),""}}}function ye(){return new Date().toISOString().substring(0,10)}function Pr(t,e=kr){const r=[];let i=t.slice();for(const n of t){const s=r.find(o=>o.agent===n.agent);if(s){if(s.dates.push(n.date),Ae(r)>e){s.dates.pop();break}}else if(r.push({agent:n.agent,dates:[n.date]}),Ae(r)>e){r.pop();break}i=i.slice(1)}return{heartbeatsToSend:r,unsentEntries:i}}class Br{constructor(e){this.app=e,this._canUseIndexedDBPromise=this.runIndexedDBEnvironmentCheck()}async runIndexedDBEnvironmentCheck(){return te()?ut().then(()=>!0).catch(()=>!1):!1}async read(){if(await this._canUseIndexedDBPromise){const r=await Dr(this.app);return r!=null&&r.heartbeats?r:{heartbeats:[]}}else return{heartbeats:[]}}async overwrite(e){var r;if(await this._canUseIndexedDBPromise){const n=await this.read();return we(this.app,{lastSentHeartbeatDate:(r=e.lastSentHeartbeatDate)!==null&&r!==void 0?r:n.lastSentHeartbeatDate,heartbeats:e.heartbeats})}else return}async add(e){var r;if(await this._canUseIndexedDBPromise){const n=await this.read();return we(this.app,{lastSentHeartbeatDate:(r=e.lastSentHeartbeatDate)!==null&&r!==void 0?r:n.lastSentHeartbeatDate,heartbeats:[...n.heartbeats,...e.heartbeats]})}else return}}function Ae(t){return P(JSON.stringify({version:2,heartbeats:t})).length}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Nr(t){T(new y("platform-logger",e=>new Wt(e),"PRIVATE")),T(new y("heartbeat",e=>new Rr(e),"PRIVATE")),A(J,Ee,t),A(J,Ee,"esm2017"),A("fire-js","")}Nr("");/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Q=new Map,Le={activated:!1,tokenObservers:[]},Mr={initialized:!1,enabled:!1};function u(t){return Q.get(t)||Object.assign({},Le)}function xr(t,e){return Q.set(t,e),Q.get(t)}function $(){return Mr}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const oe="https://content-firebaseappcheck.googleapis.com/v1",$r="exchangeRecaptchaV3Token",Hr="exchangeRecaptchaEnterpriseToken",Lr="exchangeDebugToken",Te={OFFSET_DURATION:5*60*1e3,RETRIAL_MIN_WAIT:30*1e3,RETRIAL_MAX_WAIT:16*60*1e3},Fr=24*60*60*1e3;/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class jr{constructor(e,r,i,n,s){if(this.operation=e,this.retryPolicy=r,this.getWaitDuration=i,this.lowerBound=n,this.upperBound=s,this.pending=null,this.nextErrorWaitInterval=n,n>s)throw new Error("Proactive refresh lower bound greater than upper bound!")}start(){this.nextErrorWaitInterval=this.lowerBound,this.process(!0).catch(()=>{})}stop(){this.pending&&(this.pending.reject("cancelled"),this.pending=null)}isRunning(){return!!this.pending}async process(e){this.stop();try{this.pending=new w,this.pending.promise.catch(r=>{}),await zr(this.getNextRun(e)),this.pending.resolve(),await this.pending.promise,this.pending=new w,this.pending.promise.catch(r=>{}),await this.operation(),this.pending.resolve(),await this.pending.promise,this.process(!0).catch(()=>{})}catch(r){this.retryPolicy(r)?this.process(!1).catch(()=>{}):this.stop()}}getNextRun(e){if(e)return this.nextErrorWaitInterval=this.lowerBound,this.getWaitDuration();{const r=this.nextErrorWaitInterval;return this.nextErrorWaitInterval*=2,this.nextErrorWaitInterval>this.upperBound&&(this.nextErrorWaitInterval=this.upperBound),r}}}function zr(t){return new Promise(e=>{setTimeout(e,t)})}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Ur={"already-initialized":"You have already called initializeAppCheck() for FirebaseApp {$appName} with different options. To avoid this error, call initializeAppCheck() with the same options as when it was originally called. This will return the already initialized instance.","use-before-activation":"App Check is being used before initializeAppCheck() is called for FirebaseApp {$appName}. Call initializeAppCheck() before instantiating other Firebase services.","fetch-network-error":"Fetch failed to connect to a network. Check Internet connection. Original error: {$originalErrorMessage}.","fetch-parse-error":"Fetch client could not parse response. Original error: {$originalErrorMessage}.","fetch-status-error":"Fetch server returned an HTTP error status. HTTP status: {$httpStatus}.","storage-open":"Error thrown when opening storage. Original error: {$originalErrorMessage}.","storage-get":"Error thrown when reading from storage. Original error: {$originalErrorMessage}.","storage-set":"Error thrown when writing to storage. Original error: {$originalErrorMessage}.","recaptcha-error":"ReCAPTCHA error.",throttled:"Requests throttled due to {$httpStatus} error. Attempts allowed again after {$time}"},f=new re("appCheck","AppCheck",Ur);/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function N(t=!1){var e;return t?(e=self.grecaptcha)===null||e===void 0?void 0:e.enterprise:self.grecaptcha}function ae(t){if(!u(t).activated)throw f.create("use-before-activation",{appName:t.name})}function ce(t){const e=Math.round(t/1e3),r=Math.floor(e/(3600*24)),i=Math.floor((e-r*3600*24)/3600),n=Math.floor((e-r*3600*24-i*3600)/60),s=e-r*3600*24-i*3600-n*60;let o="";return r&&(o+=O(r)+"d:"),i&&(o+=O(i)+"h:"),o+=O(n)+"m:"+O(s)+"s",o}function O(t){return t===0?"00":t>=10?t.toString():"0"+t}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function H({url:t,body:e},r){const i={"Content-Type":"application/json"},n=r.getImmediate({optional:!0});if(n){const l=await n.getHeartbeatsHeader();l&&(i["X-Firebase-Client"]=l)}const s={method:"POST",body:JSON.stringify(e),headers:i};let o;try{o=await fetch(t,s)}catch(l){throw f.create("fetch-network-error",{originalErrorMessage:l==null?void 0:l.message})}if(o.status!==200)throw f.create("fetch-status-error",{httpStatus:o.status});let a;try{a=await o.json()}catch(l){throw f.create("fetch-parse-error",{originalErrorMessage:l==null?void 0:l.message})}const c=a.ttl.match(/^([\d.]+)(s)$/);if(!c||!c[2]||isNaN(Number(c[1])))throw f.create("fetch-parse-error",{originalErrorMessage:`ttl field (timeToLive) is not in standard Protobuf Duration format: ${a.ttl}`});const h=Number(c[1])*1e3,p=Date.now();return{token:a.token,expireTimeMillis:p+h,issuedAtTimeMillis:p}}function Vr(t,e){const{projectId:r,appId:i,apiKey:n}=t.options;return{url:`${oe}/projects/${r}/apps/${i}:${$r}?key=${n}`,body:{recaptcha_v3_token:e}}}function Wr(t,e){const{projectId:r,appId:i,apiKey:n}=t.options;return{url:`${oe}/projects/${r}/apps/${i}:${Hr}?key=${n}`,body:{recaptcha_enterprise_token:e}}}function Fe(t,e){const{projectId:r,appId:i,apiKey:n}=t.options;return{url:`${oe}/projects/${r}/apps/${i}:${Lr}?key=${n}`,body:{debug_token:e}}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Kr="firebase-app-check-database",Gr=1,S="firebase-app-check-store",je="debug-token";let R=null;function ze(){return R||(R=new Promise((t,e)=>{try{const r=indexedDB.open(Kr,Gr);r.onsuccess=i=>{t(i.target.result)},r.onerror=i=>{var n;e(f.create("storage-open",{originalErrorMessage:(n=i.target.error)===null||n===void 0?void 0:n.message}))},r.onupgradeneeded=i=>{const n=i.target.result;switch(i.oldVersion){case 0:n.createObjectStore(S,{keyPath:"compositeKey"})}}}catch(r){e(f.create("storage-open",{originalErrorMessage:r==null?void 0:r.message}))}}),R)}function qr(t){return Ve(We(t))}function Jr(t,e){return Ue(We(t),e)}function Xr(t){return Ue(je,t)}function Yr(){return Ve(je)}async function Ue(t,e){const i=(await ze()).transaction(S,"readwrite"),s=i.objectStore(S).put({compositeKey:t,value:e});return new Promise((o,a)=>{s.onsuccess=c=>{o()},i.onerror=c=>{var h;a(f.create("storage-set",{originalErrorMessage:(h=c.target.error)===null||h===void 0?void 0:h.message}))}})}async function Ve(t){const r=(await ze()).transaction(S,"readonly"),n=r.objectStore(S).get(t);return new Promise((s,o)=>{n.onsuccess=a=>{const c=a.target.result;s(c?c.value:void 0)},r.onerror=a=>{var c;o(f.create("storage-get",{originalErrorMessage:(c=a.target.error)===null||c===void 0?void 0:c.message}))}})}function We(t){return`${t.options.appId}-${t.name}`}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const C=new Ne("@firebase/app-check");/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function Qr(t){if(te()){let e;try{e=await qr(t)}catch(r){C.warn(`Failed to read token from IndexedDB. Error: ${r}`)}return e}}function V(t,e){return te()?Jr(t,e).catch(r=>{C.warn(`Failed to write token to IndexedDB. Error: ${r}`)}):Promise.resolve()}async function Zr(){let t;try{t=await Yr()}catch{}if(t)return t;{const e=Et();return Xr(e).catch(r=>C.warn(`Failed to persist debug token to IndexedDB. Error: ${r}`)),e}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function le(){return $().enabled}async function he(){const t=$();if(t.enabled&&t.token)return t.token.promise;throw Error(`
            Can't get debug token in production mode.
        `)}function en(){const t=Re(),e=$();if(e.initialized=!0,typeof t.FIREBASE_APPCHECK_DEBUG_TOKEN!="string"&&t.FIREBASE_APPCHECK_DEBUG_TOKEN!==!0)return;e.enabled=!0;const r=new w;e.token=r,typeof t.FIREBASE_APPCHECK_DEBUG_TOKEN=="string"?r.resolve(t.FIREBASE_APPCHECK_DEBUG_TOKEN):r.resolve(Zr())}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const tn={error:"UNKNOWN_ERROR"};function rn(t){return ee.encodeString(JSON.stringify(t),!1)}async function Z(t,e=!1){const r=t.app;ae(r);const i=u(r);let n=i.token,s;if(n&&!v(n)&&(i.token=void 0,n=void 0),!n){const c=await i.cachedTokenPromise;c&&(v(c)?n=c:await V(r,void 0))}if(!e&&n&&v(n))return{token:n.token};let o=!1;if(le()){i.exchangeTokenPromise||(i.exchangeTokenPromise=H(Fe(r,await he()),t.heartbeatServiceProvider).finally(()=>{i.exchangeTokenPromise=void 0}),o=!0);const c=await i.exchangeTokenPromise;return await V(r,c),i.token=c,{token:c.token}}try{i.exchangeTokenPromise||(i.exchangeTokenPromise=i.provider.getToken().finally(()=>{i.exchangeTokenPromise=void 0}),o=!0),n=await u(r).exchangeTokenPromise}catch(c){c.code==="appCheck/throttled"?C.warn(c.message):C.error(c),s=c}let a;return n?s?v(n)?a={token:n.token,internalError:s}:a=Se(s):(a={token:n.token},i.token=n,await V(r,n)):a=Se(s),o&&Ke(r,a),a}async function nn(t){const e=t.app;ae(e);const{provider:r}=u(e);if(le()){const i=await he(),{token:n}=await H(Fe(e,i),t.heartbeatServiceProvider);return{token:n}}else{const{token:i}=await r.getToken();return{token:i}}}function de(t,e,r,i){const{app:n}=t,s=u(n),o={next:r,error:i,type:e};if(s.tokenObservers=[...s.tokenObservers,o],s.token&&v(s.token)){const a=s.token;Promise.resolve().then(()=>{r({token:a.token}),Ie(t)}).catch(()=>{})}s.cachedTokenPromise.then(()=>Ie(t))}function ue(t,e){const r=u(t),i=r.tokenObservers.filter(n=>n.next!==e);i.length===0&&r.tokenRefresher&&r.tokenRefresher.isRunning()&&r.tokenRefresher.stop(),r.tokenObservers=i}function Ie(t){const{app:e}=t,r=u(e);let i=r.tokenRefresher;i||(i=sn(t),r.tokenRefresher=i),!i.isRunning()&&r.isTokenAutoRefreshEnabled&&i.start()}function sn(t){const{app:e}=t;return new jr(async()=>{const r=u(e);let i;if(r.token?i=await Z(t,!0):i=await Z(t),i.error)throw i.error;if(i.internalError)throw i.internalError},()=>!0,()=>{const r=u(e);if(r.token){let i=r.token.issuedAtTimeMillis+(r.token.expireTimeMillis-r.token.issuedAtTimeMillis)*.5+3e5;const n=r.token.expireTimeMillis-5*60*1e3;return i=Math.min(i,n),Math.max(0,i-Date.now())}else return 0},Te.RETRIAL_MIN_WAIT,Te.RETRIAL_MAX_WAIT)}function Ke(t,e){const r=u(t).tokenObservers;for(const i of r)try{i.type==="EXTERNAL"&&e.error!=null?i.error(e.error):i.next(e)}catch{}}function v(t){return t.expireTimeMillis-Date.now()>0}function Se(t){return{token:rn(tn),error:t}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class on{constructor(e,r){this.app=e,this.heartbeatServiceProvider=r}_delete(){const{tokenObservers:e}=u(this.app);for(const r of e)ue(this.app,r.next);return Promise.resolve()}}function an(t,e){return new on(t,e)}function cn(t){return{getToken:e=>Z(t,e),getLimitedUseToken:()=>nn(t),addTokenListener:e=>de(t,"INTERNAL",e),removeTokenListener:e=>ue(t.app,e)}}const ln="@firebase/app-check",hn="0.8.8";/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const dn="https://www.google.com/recaptcha/api.js",un="https://www.google.com/recaptcha/enterprise.js";function fn(t,e){const r=new w,i=u(t);i.reCAPTCHAState={initialized:r};const n=Ge(t),s=N(!1);return s?M(t,e,s,n,r):mn(()=>{const o=N(!1);if(!o)throw new Error("no recaptcha");M(t,e,o,n,r)}),r.promise}function pn(t,e){const r=new w,i=u(t);i.reCAPTCHAState={initialized:r};const n=Ge(t),s=N(!0);return s?M(t,e,s,n,r):bn(()=>{const o=N(!0);if(!o)throw new Error("no recaptcha");M(t,e,o,n,r)}),r.promise}function M(t,e,r,i,n){r.ready(()=>{gn(t,e,r,i),n.resolve(r)})}function Ge(t){const e=`fire_app_check_${t.name}`,r=document.createElement("div");return r.id=e,r.style.display="none",document.body.appendChild(r),e}async function qe(t){ae(t);const r=await u(t).reCAPTCHAState.initialized.promise;return new Promise((i,n)=>{const s=u(t).reCAPTCHAState;r.ready(()=>{i(r.execute(s.widgetId,{action:"fire_app_check"}))})})}function gn(t,e,r,i){const n=r.render(i,{sitekey:e,size:"invisible",callback:()=>{u(t).reCAPTCHAState.succeeded=!0},"error-callback":()=>{u(t).reCAPTCHAState.succeeded=!1}}),s=u(t);s.reCAPTCHAState=Object.assign(Object.assign({},s.reCAPTCHAState),{widgetId:n})}function mn(t){const e=document.createElement("script");e.src=dn,e.onload=t,document.head.appendChild(e)}function bn(t){const e=document.createElement("script");e.src=un,e.onload=t,document.head.appendChild(e)}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Je{constructor(e){this._siteKey=e,this._throttleData=null}async getToken(){var e,r,i;Ze(this._throttleData);const n=await qe(this._app).catch(o=>{throw f.create("recaptcha-error")});if(!(!((e=u(this._app).reCAPTCHAState)===null||e===void 0)&&e.succeeded))throw f.create("recaptcha-error");let s;try{s=await H(Vr(this._app,n),this._heartbeatServiceProvider)}catch(o){throw!((r=o.code)===null||r===void 0)&&r.includes("fetch-status-error")?(this._throttleData=Qe(Number((i=o.customData)===null||i===void 0?void 0:i.httpStatus),this._throttleData),f.create("throttled",{time:ce(this._throttleData.allowRequestsAfter-Date.now()),httpStatus:this._throttleData.httpStatus})):o}return this._throttleData=null,s}initialize(e){this._app=e,this._heartbeatServiceProvider=se(e,"heartbeat"),fn(e,this._siteKey).catch(()=>{})}isEqual(e){return e instanceof Je?this._siteKey===e._siteKey:!1}}class Xe{constructor(e){this._siteKey=e,this._throttleData=null}async getToken(){var e,r,i;Ze(this._throttleData);const n=await qe(this._app).catch(o=>{throw f.create("recaptcha-error")});if(!(!((e=u(this._app).reCAPTCHAState)===null||e===void 0)&&e.succeeded))throw f.create("recaptcha-error");let s;try{s=await H(Wr(this._app,n),this._heartbeatServiceProvider)}catch(o){throw!((r=o.code)===null||r===void 0)&&r.includes("fetch-status-error")?(this._throttleData=Qe(Number((i=o.customData)===null||i===void 0?void 0:i.httpStatus),this._throttleData),f.create("throttled",{time:ce(this._throttleData.allowRequestsAfter-Date.now()),httpStatus:this._throttleData.httpStatus})):o}return this._throttleData=null,s}initialize(e){this._app=e,this._heartbeatServiceProvider=se(e,"heartbeat"),pn(e,this._siteKey).catch(()=>{})}isEqual(e){return e instanceof Xe?this._siteKey===e._siteKey:!1}}class Ye{constructor(e){this._customProviderOptions=e}async getToken(){const e=await this._customProviderOptions.getToken(),r=mt(e.token),i=r!==null&&r<Date.now()&&r>0?r*1e3:Date.now();return Object.assign(Object.assign({},e),{issuedAtTimeMillis:i})}initialize(e){this._app=e}isEqual(e){return e instanceof Ye?this._customProviderOptions.getToken.toString()===e._customProviderOptions.getToken.toString():!1}}function Qe(t,e){if(t===404||t===403)return{backoffCount:1,allowRequestsAfter:Date.now()+Fr,httpStatus:t};{const r=e?e.backoffCount:0,i=Tt(r,1e3,2);return{backoffCount:r+1,allowRequestsAfter:Date.now()+i,httpStatus:t}}}function Ze(t){if(t&&Date.now()-t.allowRequestsAfter<=0)throw f.create("throttled",{time:ce(t.allowRequestsAfter-Date.now()),httpStatus:t.httpStatus})}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Yn(t=Ir(),e){t=It(t);const r=se(t,"app-check");if($().initialized||en(),le()&&he().then(n=>console.log(`App Check debug token: ${n}. You will need to add it to your app's App Check settings in the Firebase console for it to work.`)),r.isInitialized()){const n=r.getImmediate(),s=r.getOptions();if(s.isTokenAutoRefreshEnabled===e.isTokenAutoRefreshEnabled&&s.provider.isEqual(e.provider))return n;throw f.create("already-initialized",{appName:t.name})}const i=r.initialize({options:e});return _n(t,e.provider,e.isTokenAutoRefreshEnabled),u(t).isTokenAutoRefreshEnabled&&de(i,"INTERNAL",()=>{}),i}function _n(t,e,r){const i=xr(t,Object.assign({},Le));i.activated=!0,i.provider=e,i.cachedTokenPromise=Qr(t).then(n=>(n&&v(n)&&(i.token=n,Ke(t,{token:n.token})),n)),i.isTokenAutoRefreshEnabled=r===void 0?t.automaticDataCollectionEnabled:r,i.provider.initialize(t)}function Qn(t,e,r,i){let n=()=>{},s=()=>{};return e.next!=null?n=e.next.bind(e):n=e,e.error!=null&&(s=e.error.bind(e)),de(t,"EXTERNAL",n,s),()=>ue(t.app,n)}const En="app-check",Ce="app-check-internal";function vn(){T(new y(En,t=>{const e=t.getProvider("app").getImmediate(),r=t.getProvider("heartbeat");return an(e,r)},"PUBLIC").setInstantiationMode("EXPLICIT").setInstanceCreatedCallback((t,e,r)=>{t.getProvider(Ce).initialize()})),T(new y(Ce,t=>{const e=t.getProvider("app-check").getImmediate();return cn(e)},"PUBLIC").setInstantiationMode("EXPLICIT")),A(ln,hn)}vn();var wn="firebase",yn="10.14.1";/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */A(wn,yn,"app");export{Nn as A,pe as B,y as C,$n as D,re as E,D as F,Gn as G,Vn as H,ee as I,Hn as J,Fn as K,Ne as L,rt as M,Kn as N,Pn as O,w as P,xn as Q,Mn as R,Xn as S,qn as T,An as U,st as V,Tr as W,Je as X,Xe as Y,Ye as Z,T as _,On as a,kn as b,Jn as c,It as d,Wn as e,d as f,In as g,Be as h,Cn as i,W as j,se as k,K as l,Dn as m,Rn as n,zn as o,Un as p,jn as q,A as r,Ln as s,Ir as t,Tn as u,Sn as v,Bn as w,Yn as x,Qn as y,tt as z};
//# sourceMappingURL=vAnVaY50.js.map
