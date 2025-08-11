<?php if (!$arResult['IBLOCK_ID']): ?>
  <div class="city-selector">Не задан инфоблок в настройках модуля.</div>
  <?php return; endif; ?>
<div class="city-selector">
  <button type="button" class="js-city-open">Выбрать город</button>

  <div class="city-modal" style="display:none">
    <div class="city-modal__inner">
      <button class="js-city-close" type="button">×</button>
      <input type="search" class="js-city-search" placeholder="Поиск города">

      <div class="city-modal__list">
        <?php foreach ($arResult['CITIES'] as $c): ?>
          <div class="city-item" data-id="<?=$c['ID']?>"><?=$c['NAME']?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<script>
BX.ready(function(){
  var root = document.currentScript.parentNode;
  if(!root) return;
  var btn = root.querySelector('.js-city-open');
  var modal = root.querySelector('.city-modal');
  var close = root.querySelector('.js-city-close');
  var items = root.querySelectorAll('.city-item');

  function open(){ modal.style.display='block'; }
  function hide(){ modal.style.display='none'; }

  if(btn) btn.addEventListener('click', open);
  if(close) close.addEventListener('click', hide);

  items.forEach(function(el){
    el.addEventListener('click', function(){
      var id = parseInt(this.getAttribute('data-id')||'0',10);
      BX.ajax.runComponentAction('qwelp.site_location:city.selector','select',{
        mode: 'class',
        data: { cityId: id }
      }).then(function(){
        hide();
        document.dispatchEvent(new CustomEvent('qwelp:cityChanged',{detail:{id:id}}));
      });
    });
  });
});
</script>
