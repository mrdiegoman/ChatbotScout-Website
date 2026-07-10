# Google Pay a Apple Pay cez Stripe — implementačný cookbook

## Rozhodnutie architektúry

Google Pay a Apple Pay sú v tomto projekte **wallet platobné možnosti vo Stripe**, nie druhé
platobné brány. Preto nesmú mať vlastný `PaymentIntent`, vlastnú databázovú platbu ani samostatný
webhook.

Súčasný tok zostáva jediným zdrojom pravdy:

```text
Checkout UI
  -> Stripe Subscription (default_incomplete)
  -> invoice PaymentIntent
  -> Stripe Payment Element (karta, Google Pay alebo Apple Pay)
  -> invoice.paid / invoice.payment_succeeded webhook
  -> inc/stripePaymentFinalizer.php
  -> local payments + subscriptions + promotions
```

Takto obe wallet metódy dedia rovnakú ochranu proti duplicitám, aktiváciu predplatného, faktúry,
obnovy, zlyhané platby a úspešnú stránku ako platba kartou cez Stripe.

## Čo je v kóde už zapojené

- `public/js/checkout.js` vytvára existujúci Stripe Payment Element s `googlePay: 'auto'` a `applePay: 'auto'`; dostupné wallet metódy dáva pred kartu.
- Pri už uloženej platobnej metóde zákazník môže zvoliť **Use a different payment method**. Tým sa opäť otvorí Stripe Payment Element a môže sa zobraziť Google Pay alebo Apple Pay.
- `public/app/stripeActions.php` už používa `payment_settings.save_default_payment_method = on_subscription`. Po úspešnej prvej wallet platbe Stripe uloží zvolenú tokenizovanú kartu ako predvolenú metódu predplatného pre ďalšie faktúry.
- `public/api/stripeWebhook.php`, `inc/stripePaymentFinalizer.php` a `public/payment-success.php` sa nemenia: Google Pay aj Apple Pay posielajú rovnaké Stripe udalosti faktúry a platobného zámeru ako karta.

Nedávajte do prehliadača žiadny Stripe secret key, Google Pay alebo Apple Pay token, ani merchant secret. Na webe spravovanom cez Stripe ich netreba.

## Povinné nastavenie v Stripe Dashboard

Kód sám nestačí; bez týchto nastavení Stripe wallet metódy správne nezobrazí.

1. V Stripe Dashboard prepnite najprv na **test mode** a v sekcii **Payment methods** povoľte Google Pay a Apple Pay, ak ešte nie sú aktívne.
2. Otvorte **Settings → Payment method domains** a pridajte presne každý HTTPS host, kde sa môže zobraziť checkout:
   - `chatbotscout.com`
   - `www.chatbotscout.com`, ak ho reálne používate
   - samostatný staging host, ak existuje
3. Pri integrácii s `Stripe.js` nie je potrebné registrovať URL cestu; registruje sa doména/host. `https://chatbotscout.com` a `https://chatbotscout.com/public` sú teda jedna registrácia.
4. Overte, že doména je v Dashboarde **enabled**. Registrácia v test/sandbox režime nenahrádza kontrolu live režimu pred produkciou.
5. Po nasadení zopakujte kontrolu v **live mode**. Stripe uvádza, že pri Elements treba registrovať aj subdomény a že pri lokálnom teste je nutná verejná HTTPS adresa (napríklad cez ngrok); `localhost` nemôže byť produkčnou Google Pay doménou.

Nie je potrebné vytvárať samostatné Google Pay alebo Apple Pay API volanie ani druhý webhook. Pri Apple Pay cez Stripe.js/Elements Stripe zabezpečuje merchant validation; netreba vytvárať vlastný Apple Merchant ID ani Apple Pay certifikát.

## Prečo sa Google Pay alebo Apple Pay nemusí zobraziť

`googlePay: 'auto'` a `applePay: 'auto'` sú úmyselné. Wallet sa zobrazí len, keď Stripe vidí podporované zariadenie, prehliadač a vhodnú kartu vo Wallet. Google Pay zvyčajne vyžaduje kompatibilný Chrome/Android alebo Google Wallet; Apple Pay vyžaduje Safari na kompatibilnom iPhone, iPade alebo Macu s kartou v Apple Wallet. Bežné dôvody, prečo sa nezobrazí:

- nepodporovaný prehliadač alebo zariadenie;
- žiadna karta v Google Wallet/Apple Wallet alebo nepodporovaná karta/vydavateľ;
- nezaregistrovaná alebo zakázaná doména v Stripe;
- Google Pay alebo Apple Pay nie je aktívny pre Stripe účet alebo danú krajinu/menu;
- lokálne HTTP prostredie alebo nesprávny host.

V takom prípade sa používateľovi bezpečne zobrazí štandardný Stripe formulár pre kartu. Nejde o chybu checkoutu.

## Testovací scenár bez reálnej platby

Predpoklady: Stripe test kľúče v `config/stripe.php`, checkout host je cez HTTPS verejne dostupný a je zaregistrovaný v Stripe test mode, Google účet má kartu vo Wallet.

1. Spustite lokálne prijímanie webhookov:

   ```powershell
   stripe listen --forward-to http://localhost/MainWebsite/public/api/stripeWebhook.php
   ```

   Skopírujte webhook signing secret z príkazu len do lokálneho `config/stripe.php`; nikdy ho necommitujte.

2. Otvorte checkout v Chrome na Android zariadení alebo v Chrome s prihláseným Google účtom. Zaškrtnite business potvrdenie — až potom aplikácia bezpečne vytvorí Stripe PaymentIntent a načíta Payment Element.
3. V Payment Elemente vyberte Google Pay alebo Apple Pay a dokončite testovaciu autorizáciu. Pri existujúcej uloženej karte najprv kliknite **Use a different payment method**.
4. Overte v Stripe Dashboarde, že existujú Subscription, Invoice a PaymentIntent a že faktúra je `paid`.
5. Overte doručenie `invoice.paid` alebo `invoice.payment_succeeded` do webhooku. Až tento krok má aktivovať lokálnu promotion/subscription.
6. Overte `payment-success` a profil používateľa. Skontrolujte, že nová metóda je predvolená pre predplatné a že neexistuje duplicitná lokálna platba alebo promotion.

Pre test zlyhania a SCA použite Stripe test režim a príslušné Stripe testovacie karty. Nevytvárajte testovacie platby vo live mode.

## Produkčný release checklist

- [ ] Google Pay a Apple Pay sú aktívne v Stripe **live mode**.
- [ ] Každá produkčná checkout doména a subdoména je registrovaná a enabled v **Payment method domains**.
- [ ] `config/stripe.php` obsahuje live publishable key, secret key a live webhook secret; tento súbor zostáva mimo Gitu.
- [ ] Endpoint `https://<vas-host>/public/api/stripeWebhook.php` je v Stripe live webhook nastavení a počúva aspoň `invoice.paid`, `invoice.payment_succeeded`, `invoice.payment_failed`, `invoice.voided`, `customer.subscription.created`, `customer.subscription.updated` a `customer.subscription.deleted`.
- [ ] Produkčný test sa robí so skutočným Google Wallet a Apple Wallet účtom a malou, autorizovanou platbou; po overení sa podľa potreby refunduje cez Stripe.
- [ ] Privacy Policy a Terms obsahujú aktuálne zmienky o Stripe, Google Pay a Apple Pay. Pred publikovaním právnych textov ich nechajte skontrolovať právnikom, ktorý pozná vaše sídlo, skutočne používané wallet služby a obchodný model.

## Zdroje

- [Stripe: Google Pay](https://docs.stripe.com/google-pay)
- [Stripe: Apple Pay](https://docs.stripe.com/apple-pay)
- [Stripe: Register domains for payment methods](https://docs.stripe.com/payments/payment-methods/pmd-registration)
- [Stripe.js: Payment Element wallet behavior](https://docs.stripe.com/js/element/events/on_loaderstart?type=ibanElement)
