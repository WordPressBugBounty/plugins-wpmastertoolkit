!function(){var e={595:function(){document.addEventListener("DOMContentLoaded",(()=>(()=>{const e=document.querySelector(".wrap.wp-mastertoolkit form"),t=document.querySelector('input[name="wpmastertoolkit_settings_child_theme_generator[action]"]'),n=document.getElementById("child-theme-download-zip"),r=document.getElementById("child-theme-generate"),o=document.getElementById("child-theme-generate-and-activate");n.addEventListener("click",(()=>{t.value="download",e.target="_blank"})),r.addEventListener("click",(()=>{t.value="generate",e.target=""})),o.addEventListener("click",(()=>{t.value="generate-and-activate",e.target=""}))})()))},1642:function(){const e=()=>{const e="wpmastertoolkit_settings_child_theme_generator",t=document.getElementById("preview-child-theme"),n=document.getElementById("preview-child-theme-container"),r=document.getElementById("close-preview-child-theme"),o=wpmastertoolkit_child_theme_generator.themes_folders,d=document.querySelector('input[name="'+e+'[child_theme_name]"]'),i=(document.querySelector('input[name="'+e+'[child_theme_uri]"]'),document.querySelector('input[name="'+e+'[child_theme_version]"]')),c=document.querySelector('input[name="'+e+'[child_theme_author]"]'),l=document.querySelector('input[name="'+e+'[child_theme_author_uri]"]'),a=document.querySelector('textarea[name="'+e+'[child_theme_description]"]'),u=document.querySelector('input[name="'+e+'[child_theme_tags]"]'),m=document.querySelector('input[name="child_theme_screenshot"]'),h=document.querySelector('input[name="'+e+'[child_theme_folder_name]"]'),s=document.getElementById("child-theme-name"),_=document.getElementById("child-theme-version"),p=document.getElementById("child-theme-author"),v=document.getElementById("child-theme-description"),y=document.getElementById("child-theme-tags"),g=document.getElementById("child-theme-screenshot"),f=document.getElementById("child-theme-folder-name");t.addEventListener("click",(e=>{e.preventDefault(),s.innerText=d.value,_.innerText=i.value,p.innerText=""==c.value?wpmastertoolkit_child_theme_generator.i18n.anonymous:c.value,p.href=l.value,v.innerText=a.value,y.innerText=u.value,f.innerText=h.value,(()=>{if(""===m.value)g.src=g.getAttribute("data-default-src");else{const e=new FileReader;e.onload=function(e){g.src=e.target.result},e.readAsDataURL(m.files[0])}})(),n.style.display="block"})),r.addEventListener("click",(e=>{e.preventDefault(),n.style.display="none"})),h.addEventListener("input",(()=>{o.includes(h.value)?(h.setCustomValidity(wpmastertoolkit_child_theme_generator.i18n.folder_already_exist),h.reportValidity()):h.setCustomValidity("")}))};document.addEventListener("DOMContentLoaded",(()=>e()))}},t={};function n(r){var o=t[r];if(void 0!==o)return o.exports;var d=t[r]={exports:{}};return e[r](d,d.exports,n),d.exports}n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,{a:t}),t},n.d=function(e,t){for(var r in t)n.o(t,r)&&!n.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},function(){"use strict";n(1642),n(595)}()}();