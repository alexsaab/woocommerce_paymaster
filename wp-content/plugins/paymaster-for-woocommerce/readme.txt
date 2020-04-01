=== PayMaster for WooCommerce ===
Contributors: alexsaab
Tags: paymaster, payment getaway, woo commerce, woocommerce, ecommerce
Requires at least: 3.0
Tested up to: 3.3.2
Stable tag: trunk

Allows you to use Paymaster payment gateway with the WooCommerce plugin.

== Description ==

После активации плагина через панель управления в WooCommerce прописывем
Логин мерчат, секретная фраза, метод шифрования и т.д., узнать их можно в [личном кабинете paymaster](https://paymaster.ru/partners/ru)

В Paymaster прописываем, как POST запросы:
<ul style="list-style:none;">
<li>Result URL: http://your_domain/?wc-api=wc_paymaster&paymaster=result</li
<li>Success URL: http://your_domain/?wc-api=wc_paymaster&paymaster=success</li>
<li>Fail URL: http://your_domain/?wc-api=wc_paymaster&paymaster=fail</li>
<li>Метод отсылки данных: POST</li>
</ul>

Далее ставим галки:
<ul style="list-style:none;">
<li>Не проверять уникальность номера счета для отклоненных платежей</li>
<li>Повторно отправлять Payment Notification при сбоях</li>
</ul>

Более подробно на [странице плагина](https://github.com/alexsaab/woocommerce-paymaster)


Если возникнут проблемы, dev@agaxx.ru


== Installation ==
1. Убедитесь что у вас установлена посленяя версия плагина [WooCommerce](/www.woothemes.com/woocommerce)
2. Распакуйте архив и загрузите "paymaster-for-woocommerce" в папку ваш-домен/wp-content/plugins
3. Активируйте плагин


== Changelog ==

== Changelog ==
= 1.4 =
* Ошибки устранены + совместитмость с php 7.3

= 1.3 =
* Устранены баги

= 1.2 =
* Устранены баги

= 1.1 =
* Устранены баги

= 1.0 =
* Релиз плагина