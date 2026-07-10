# Testovaci checklist a suhrn zmien

Tento subor vznikol po vacsom cleanup/audit passe, ked zmeny este nie su commitnute ani pushnute na GitHub. Stav bol kontrolovany cez `git diff --stat`, `git diff --name-only`, `git status --short` a priebezne lint kontroly.

## Aktualny rozsah zmien

- Zmenene je priblizne 93 sledovanych suborov.
- Pridane nove subory:
  - `classes/ClientIpResolver.php`
  - `public/js/admin_events.js`
  - `public/js/analyticsConsent.js`
  - `public/js/analyticsInit.js`
  - `public/js/avatarLoader.js`
  - `public/js/imageFallback.js`
- Posledne automaticke kontroly presli:
  - `PHP lint OK (108 files)`
  - `JS syntax OK (42 files)`
  - `git diff --check` presiel, zostali len Git CRLF warningy.

## Co sa spravilo

### Bezpecnost, sessions, CSRF, config

- Centralizovane a spevnene session/cookie nastavenia v `inc/bootstrap.php`, `classes/Session.php`, `config/app.php`, `config/app.example.php`.
- Pridany `ClientIpResolver`, aby sa IP adresa neriesila ad hoc po roznych endpointoch.
- Rate limiting bol zjednoteny a upraveny v `classes/RateLimiter.php`.
- Logout bol presunuty na POST + CSRF ochranu cez `public/logout.php` a formulare v headeri.
- Viac API endpointov bolo zjednotenych na konzistentne JSON odpovede a bezpecnejsie status kody.
- `Database.php` uz nema tvrde `die()` spravanie.
- `errorHandler.php` maskuje citlive parametre.
- Z `TO DO LIST.txt` boli odstranene citlive hodnoty a nahradene upozornenim, ze maju byt iba v `.env` alebo password manageri.
- Poznamka: stare realne secrets/tokens treba aj tak rotovat mimo kodu, ak boli niekedy commitnute alebo zdielane.

### Visitor tracking, consent a click tracking

- `public/api/recordVisit.php` bol spevneny proti zlym vstupom, rate-limitovany a zjednoteny.
- `public/api/identify.php` a `public/api/revokeConsent.php` boli upravene pre konzistentnejsie spravanie.
- `classes/Visitor.php` a consent/cookie flow boli dotknute kvoli lepsiemu oddeleniu funkcionality.

### Auth a user flowy

- Upravy okolo `forgotPassword.php`, `resetPassword.php`, `verifyEmail.php`, `verificationSent.php`, `resendEmail.js`.
- Upravy v `classes/User.php` a `classes/Email.php` kvoli email sanitizacii, verifikacii a reset flowu.
- Login/logout a header user state boli cistene.
- `verificationSent.php` bol zjednoduseny na jeden kratky responzivny panel bez krokov, blikajucej animacie a medzery pred footerom.
- Success aj failed stav v `verifyEmail.php` pouzivaju rovnaky jednoduchy panel, sive pozadie, tlacidla a napojenie na footer ako `verificationSent.php`; panely maju povodny `2rem` radius z uspesneho overenia.
- Pending verification email sa drzi v serverovej session a uz sa neposiela v URL query parametri.
- Resend countdown vychadza zo serveroveho casu; `localStorage` uz nerozhoduje o tom, ci je resend povoleny.
- Serverovy resend cooldown sa uklada do `users.verification_email_sent_at`, takze ho nevypne vypnutie alebo uprava JavaScriptu.
- Prvy verifikacny email po registracii nechava `verification_email_sent_at` prazdne; 60-sekundovy cooldown zacne az explicitnym kliknutim na Resend.
- Pridana DB migracia `WebsiteDB/2026_07_10_verification_resend_cooldown.sql`; na lokalnej databaze bola aplikovana.
- Password requirements na sign up stranke su zhrnute do jednej kratkej, statickej a neutralnej vety; pocas pisania uz neukazuju splnene pravidla po jednom.
- Password reset token sa spotrebuje atomovo spolu so zmenou hesla, pouzity link uz nezobrazi formular, failure stav zdiela dizajn s neuspesnou email verifikaciou a uspesny reset zrusi existujuce prihlasene sessions.

### Header, sidebar, cookies, shared JS

- `inc/header.php` bol cisteny od inline JS bootstrapov.
- Pridany `public/js/avatarLoader.js` pre nacitanie avatarov mimo inline skriptu.
- Pridany `public/js/imageFallback.js` pre fallback obrazkov namiesto inline `onerror`.
- Pridane `public/js/analyticsConsent.js` a `public/js/analyticsInit.js`, aby Google Analytics bootstrap nebol inline.
- Fixnuty email eye toggle v profile dropdown-e:
  - `ProfileDropdown.js` nastavuje pociatocny icon stav.
  - `main.css` ma pravidlo, aby `svg[hidden]` v headeri naozaj zmizlo.
- Mobilny sidebar `Log Out` bol zjednoteny s ostatnymi polozkami velkostou a spacingom.
- `footer.php`, `cookie.js`, `cookie.css` boli cistene na `hidden` stav a jasnejsie togglovanie.

### Inline JS, inline event handlery, CSP hygiene

- Odstranene executable inline JS bloky z:
  - `public/chatbotShowcase.php`
  - `public/contact.php`
  - `public/advertise.php`
  - `public/checkout.php`
  - `inc/header.php`
- Data sa teraz odovzdavaju cez `data-*` atributy alebo externe JS subory.
- Scan na inline event handlery a `javascript:` odkazy je cisty.
- Zostava len:
  - external Google script s nonce v `inc/header.php`
  - JSON-LD structured data skripty s nonce v `public/index.php`
  - to je ocakavane a nie je to executable inline app logic.

### Admin cast

- `public/admin.php`, `public/js/admin.js`, `public/js/admin_refresh.js`, `public/js/admin_events.js`, `public/css/admin.css`, `public/css/admin_settings.css` boli cistene.
- Inline event logika bola presunuta do JS.
- Admin akcie v `public/app/admin*Actions.php` a API endpointy pre admin notification emails/block/delete boli zjednotene.
- CSP hygiene v admin UI bola zlepsena.

### Chatbot showcase

- `public/chatbotShowcase.php`, `public/js/chatbotShowcase.js`, `public/css/chatbotShowcase.css`:
  - loading, empty state, pagination bottom bar, show more a rating spravy pouzivaju `hidden`.
  - login state pre JS je cez `data-user-logged-in`.
  - sort sipka a search loading su cez CSS triedy.
  - rating modal spravy su cez `hidden`.
  - filter popup a advanced feature casti boli zjednotene.
  - odstraneny duplicity bug s `advancedFeaturesContainer`.

### Favourites showcase

- `public/favouritesShowcase.php`, `public/js/favouritesShowcase.js`, `public/css/favouritesShowcase.css`:
  - stavove UI prvky pouzivaju `hidden`.
  - opraveny realny bug, kde `renderChatbots(chatbots)` pouzival neexistujucu premennu `append`.
  - filter overlay, empty state, bottom bar a rating spravy su konzistentne.
  - odstranovanie karty je cez CSS triedu `is-removing`.

### Chatbot reviews

- `public/chatbotReviews.php`, `public/css/chatbotReviews.css`:
  - rating meter uz nema inline `style="width:..."`.
  - rating overview pouziva `data-rating`.
  - breakdown bary pouzivaju `progress`.

### Profile

- `public/profile.php`, `public/js/profile.js`, `public/js/profileReviews.js`, `public/css/profile.css`, `public/css/profileReviews.css`:
  - viac modalov a stavov bolo presunutych na `hidden`.
  - profile review loading, empty state, error/success spravy su cez `hidden`.
  - edit review hviezdy pouzivaju CSS triedy namiesto runtime `style.color`.
  - delete animation je cez CSS triedu.
  - subscription/cancel/delete account modal casti boli cistene od inline style.

### Advertise a checkout

- `public/advertise.php`, `public/js/advertise.js`, `public/css/advertise.css`:
  - config je cez `#advertiseConfig` dataset.
  - modal/loading/redirect overlay stavy pouzivaju `hidden`.
  - plan UI a upgrade note boli cistene.
- `public/checkout.php`, `public/js/checkout.js`, `public/css/checkout.css`:
  - config je cez `#checkoutConfig` dataset.
  - Stripe loading, payment element, pay button loader a error state pouzivaju `hidden`.
  - loading text sa riesi cez DOM text a CSS triedy, nie inline HTML stringy tam, kde to bolo nevhodne.

### Contact

- `public/contact.php`, `public/js/contact.js`:
  - flash spravy su cez `data-contact-*` atributy.
  - inline `window._contact...` skript bol odstraneny.
  - dropdown ostal funkcny cez externy JS.

### Filters, dropdowns, popups

- `chatbotFilters.js`, `showAdvancedFeatures.js`, `platformsPopup.js`, `filterFeaturesAdding.js`, `filterIntegrationsAdding.js`, `mobileSearch.js`, `dropdownOptions.js`:
  - viac UI stavov presunutych na triedy/hidden.
  - filter badge, advanced features, integrations popup, search clear a dropdowny boli zjednotene.
- Poznamka: niektore runtime layout vypocty zostali zamerne, napriklad stabilna sirka chipov alebo custom scrollbar.

### Emails a marketing skript

- `classes/Email.php`:
  - viac vystupov bolo escapovanych/sanitizovanych.
  - opravena rozbita email template cast.
  - email HTML stale pouziva inline style, co je pri emailoch bezne a zamerne.
- `scripts/sendMarketingEmail.php`:
  - spevnene spustanie a test mode.
  - inline style v email body ostava, lebo email klienti vyzaduju inline stylovanie.

### SEO/sitemap/index

- `public/index.php`:
  - image fallback bez inline `onerror`.
  - structured data JSON-LD ostava s nonce.
- `public/sitemap.php`:
  - upravy pre konzistentnost a output.

## Co je este zamerne ponechane

- Inline CSS v emailoch (`classes/Email.php`, `scripts/sendMarketingEmail.php`) ponechat, pokial sa nerobi samostatny email templating refaktor.
- Runtime style vypocty v JS:
  - `homePage.js` carousel transform/opacity
  - `featuresSwiping.js` custom scrollbar thumb width/transform
  - `footerVertical.js` accordion maxHeight
  - `scrollLock.js` body overflow/paddingRight
  - `filterFeaturesAdding.js` stabilna minWidth pre filtre
- JSON-LD skripty na homepage ostavaju.
- External Google Analytics script ma nonce, to je normalne pri CSP.

## Pred manualnym testovanim

- Spust XAMPP Apache a MySQL.
- Otvor lokalne `http://localhost/MainWebsite/public/` alebo aktualnu lokalnu cestu, ktoru pouzivas.
- Urob hard refresh `Ctrl+F5`, lebo boli menene CSS/JS assety.
- Otvor DevTools:
  - Console musi byt bez novych JavaScript errorov.
  - Network nesmie mat 404 pre nove JS subory: `analyticsConsent.js`, `analyticsInit.js`, `avatarLoader.js`, `imageFallback.js`, `admin_events.js`.
- Pred testom si pozri:
  - `git status --short`
  - `git diff --stat`

## Automaticke kontroly pred manualnym testom

Spust z rootu projektu:

```powershell
$phpFiles = rg --files -g "*.php"; $failed = $false; foreach ($file in $phpFiles) { & C:\xampp\php\php.exe -l $file > $null; if ($LASTEXITCODE -ne 0) { Write-Output "PHP lint failed: $file"; $failed = $true } }; if (-not $failed) { Write-Output "PHP lint OK ($($phpFiles.Count) files)" }
```

```powershell
$jsFiles = rg --files public\js -g "*.js"; $failed = $false; foreach ($file in $jsFiles) { node --check $file > $null; if ($LASTEXITCODE -ne 0) { Write-Output "JS syntax failed: $file"; $failed = $true } }; if (-not $failed) { Write-Output "JS syntax OK ($($jsFiles.Count) files)" }
```

```powershell
git diff --check
```

```powershell
rg -n "<script[^>]*nonce|\son[a-z]+\s*=|javascript:" inc public classes scripts -S
```

Ocakavanie:

- PHP lint OK.
- JS syntax OK.
- `git diff --check` bez chyb, CRLF warningy su na Windowse ocakavane.
- Inline scan ukaze iba external Google script s nonce a JSON-LD structured data, nie inline app logic alebo inline event handlery.

## Manualny smoke test

- [ ] Homepage sa nacita bez PHP warningov a bez JS errorov.
- [ ] Header logo/linky funguju.
- [ ] Desktop profile dropdown sa otvara a zatvara.
- [ ] Email v profile dropdown-e je najprv maskovany.
- [ ] Po kliknuti na oko sa email odkryje.
- [ ] Pri odkryti je viditelna iba jedna ikona oka.
- [ ] Po dalsom kliknuti sa email znovu zamaskuje.
- [ ] Pri zamaskovani je viditelna iba jedna ikona oka.
- [ ] Klik mimo dropdown zavrie dropdown.
- [ ] Escape zavrie dropdown.
- [ ] Avatar sa nacita, ak ma user custom avatar.
- [ ] Ak avatar/logo neexistuje, fallback obrazok funguje.

## Header a mobilny sidebar

- [ ] Na mobile otvor burger menu.
- [ ] Menu sa otvori bez posunu layoutu a bez JS chyby.
- [ ] Linky Chatbots, Contact, About Us maju rovnaky vizualny rytmus.
- [ ] Pri prihlasenom userovi je viditelne Profile, Favourites, Log Out.
- [ ] Log Out ma konzistentny, zamerne mierne mensi font ako ostatne dropdown polozky.
- [ ] Log Out ma primeranu sirku a padding.
- [ ] Log Out odosiela POST logout a nehlasi CSRF chybu.
- [ ] Pri odhlasenom userovi su viditelne Log In a Sign Up.
- [ ] Sidebar sa zatvara cez X, klik mimo a Escape.

## Auth flow

- [ ] Login s validnym userom funguje.
- [ ] Login s neplatnym heslom ukaze chybu, nie fatal error.
- [ ] Sign up funguje s validnymi datami.
- [ ] Sign up odmietne chybny email alebo kratke heslo.
- [ ] Password requirements ostavaju pocas pisania neutralne bez tickov, zelenej farby a stavov splnene/nesplnene.
- [ ] Password requirements zaberaju na desktope jeden riadok, na mobile sa rozumne zalomia a nezatlacia suhlasy Terms ani marketing emails.
- [ ] Neplatne heslo ukaze az po blur alebo submit iba vseobecnu klientsku chybu.
- [ ] Vypnutie JavaScriptu neobide serverovu `PasswordPolicy` validaciu.
- [ ] Forgot password odosle flow bez prezradenia, ci email existuje.
- [ ] Reset password s platnym tokenom funguje.
- [ ] Reset password s neplatnym/expirnutym tokenom ukaze bezpecnu chybu.
- [ ] Opakovane otvorenie uz pouziteho reset linku nezobrazi formular a ponukne `Request a new link`.
- [ ] Pouzity alebo neplatny reset link ma rovnaky panel, pozadie, cervenu ikonu, `2rem` radius a napojenie na footer ako `Verification failed`.
- [ ] Opakovany POST s uz pouzitym reset tokenom heslo nezmeni.
- [ ] Uspesny password reset zrusi vsetky existujuce sessions daneho uctu.
- [ ] Verify email s platnym tokenom funguje.
- [ ] Verify email s neplatnym tokenom nepadne.
- [ ] Success a failed vysledok overenia pouzivaju rovnaky vizualny komponent ako `verification-sent` a maju zrozumitelne zelene/cervene stavove ikony.
- [ ] `verification-sent` a vysledok `verify-email` maju rovnake sive pozadie, `2rem` zaoblenie panelu a nulovu medzeru pred footerom.
- [ ] Resend verification email funguje a respektuje rate limit.
- [ ] Login do neovereneho uctu presmeruje na `verification-sent` bez emailu v URL.
- [ ] Verification panel zacina pod fixnym headerom; obalka sa neschovava pod header a neblika.
- [ ] Verification stranka nema zbytocne vysoky prazdny pas a footer nadvazuje priamo na hlavnu sekciu.
- [ ] Verification stranka nema horizontalny overflow na 390 px, 768 px ani desktop sirke.
- [ ] Bezprostredne po registracii je prvy Resend aktivny a nezobrazuje odpocet.
- [ ] Po kliknuti na Resend sa zobrazi iba jedno `Sending...` v tlacidle.
- [ ] Po odoslani cez Resend tlacidlo ukazuje serverovy odpocet a je pocas neho disabled pri zapnutom JS.
- [ ] Resend odpocet sa zobrazuje iba raz v tlacidle; pod tlacidlom nie je druhy timer ani prazdna medzera.
- [ ] Refresh stranky pocas odpocitu nevrati odpocet na 60 ani neaktivuje tlacidlo predcasne.
- [ ] Opakovany login do rovnakeho neovereneho uctu pocas odpocitu zobrazi zostavajuci serverovy cas.
- [ ] Po vypnuti JavaScriptu resend funguje ako bezny POST formular, ale server odmietne predcasny resend.
- [ ] Rucne odoslany POST request pocas cooldownu neposle dalsi email.
- [ ] Po uplynuti cooldownu sa da verifikacny email odoslat znova.
- [ ] Resend bez platneho CSRF tokenu vrati bezpecnu chybu a email neodosle.
- [ ] Po uspesnej verifikacii sa stary pending verification stav zo session odstrani.

## Cookie a consent

- [ ] Cookie banner sa zobrazi novemu navstevnikovi.
- [ ] Accept All ulozi consent a banner zmizne.
- [ ] Decline All ulozi odmietnutie a banner zmizne.
- [ ] Save Preferences ulozi vybrane kategorie.
- [ ] Cookie settings button otvori modal.
- [ ] Klik na tmave pozadie mimo Cookie Preferences panelu modal zatvori a odstrani scroll lock.
- [ ] Klik vo vnutri Cookie Preferences panelu ho nezatvori.
- [ ] Klaves Escape zatvori Cookie Preferences modal.
- [ ] Revoke consent API funguje bez fatal erroru.
- [ ] Analytics sa nespusta pred consentom mimo povoleneho default denied stavu.

## Chatbot showcase

- [ ] `chatbots` stranka sa nacita.
- [ ] Loading spinner sa zobrazi a potom zmizne.
- [ ] Grid view zobrazi chatbot karty.
- [ ] List view zobrazi list karty.
- [ ] Prepnutie grid/list funguje.
- [ ] Sort dropdown funguje.
- [ ] Prvy vyber `Alphabetical` zoradi chatboty A-Z so sipkou nadol; klik prepne Z-A a otoci sipku nahor.
- [ ] Sort sipka sa otoci cez triedu a zoradenie sa zmeni.
- [ ] Search funguje pre existujuce meno.
- [ ] Search s prazdnym vysledkom ukaze empty state, nie rozbity layout.
- [ ] Pagination na desktope funguje.
- [ ] Mobile Show More funguje.
- [ ] Bottom bar sa zobrazuje iba ked ma zmysel.
- [ ] Go to Website klik funguje a nezablokuje navigaciu.
- [ ] Visit tracking nevyhadzuje chybu v konzole.
- [ ] Favourites button pre prihlaseneho usera toggluje stav.
- [ ] Favourites button pre neprihlaseneho usera presmeruje na login.
- [ ] Rating hviezdy pre neprihlaseneho usera presmeruju na login.
- [ ] Rating modal pre prihlaseneho usera sa otvori.
- [ ] Rating modal odmietne submit bez hviezdy.
- [ ] Rating modal ukaze "already reviewed", ak user uz hodnotil.
- [ ] Submit review funguje a modal sa zavrie.

## Filters a platform popup

- [ ] Filters modal sa otvori.
- [ ] Filters modal sa zatvori cez X.
- [ ] Filters modal sa zatvori klikom mimo.
- [ ] Filters modal sa zatvori cez Escape.
- [ ] Feature filtre sa nacitaju.
- [ ] Advanced features show more/show less funguje.
- [ ] Filter count badge sa zobrazi iba pri aktivnych filtroch.
- [ ] Clear all vycisti filtre.
- [ ] Apply Filters zmeni vysledky.
- [ ] Live count "Found X chatbots" sa aktualizuje.
- [ ] Platforms popup sa otvori z karty.
- [ ] Platforms popup ukaze integracie alebo empty state.
- [ ] Platforms popup sa zatvori cez X, overlay a Escape.

## Favourites showcase

- [ ] `favourites` stranka vyzaduje prihlaseneho usera.
- [ ] Favourites sa nacitaju bez ReferenceError.
- [ ] Empty favourites ukaze empty state.
- [ ] Search v favourites funguje.
- [ ] Sort v favourites funguje.
- [ ] Prvy vyber `Alphabetical` vo favourites pouzije A-Z so sipkou nadol; klik prepne Z-A a sipku nahor.
- [ ] Pagination v favourites funguje.
- [ ] Filter modal vo favourites funguje.
- [ ] Remove favourite odstrani kartu animaciou.
- [ ] Po odstraneni poslednej karty sa zobrazi empty state.
- [ ] Bottom bar zmizne, ked uz nie je co strankovat.
- [ ] Rating modal vo favourites funguje rovnako ako v showcase.

## Chatbot reviews page

- [ ] Klik na review count z karty otvori review stranku.
- [ ] Review page pre existujuci chatbot zobrazi priemer a pocet reviews.
- [ ] Star meter zodpoveda ratingu.
- [ ] Breakdown toggle otvori distribuciu ratingov.
- [ ] Breakdown bary sa zobrazia cez progress.
- [ ] Klik na 5/4/3/2/1 star filter nacita reviews.
- [ ] Load more reviews funguje.
- [ ] Empty review filter ukaze empty state.
- [ ] Neexistujuci chatbot vrati pekny 404 state.

## Profile

- [ ] Profile stranka sa nacita.
- [ ] Profile tab switching funguje.
- [ ] Email toggle na profile stranke meni text a ikony spravne.
- [ ] Avatar upload funguje pre validny obrazok.
- [ ] Avatar upload odmietne zly typ/subor.
- [ ] Name edit funguje.
- [ ] Password change funguje s platnym heslom.
- [ ] Password change odmietne zle aktualne heslo.
- [ ] Marketing emails toggle funguje.
- [ ] Marketing emails toggle je pocas requestu disabled bez Windows `wait` kurzora a po odpovedi sa odomkne bez umelej pauzy.
- [ ] My Reviews tab nacita reviews.
- [ ] My Reviews empty state funguje.
- [ ] Load more reviews funguje.
- [ ] Edit review modal sa otvori.
- [ ] Edit review hviezdy sa farbia cez triedy.
- [ ] Edit review submit bez ratingu ukaze chybu.
- [ ] Edit review validny submit zmeni kartu a status na Pending.
- [ ] Delete review modal sa otvori.
- [ ] Delete review cancel zatvori modal.
- [ ] Delete review confirm odstrani kartu.
- [ ] Manage subscription modal sa otvori a zatvori.
- [ ] Cancel subscription modal funguje.
- [ ] Delete account modal funguje az po potvrdeni.

## Advertise

- [ ] Advertise page sa nacita.
- [ ] Promotion plans sa nacitaju.
- [ ] Monthly/yearly toggle prepina ceny.
- [ ] Yearly discount sa zobrazuje spravne.
- [ ] Bundle discount sa pocita spravne.
- [ ] Neprihlaseny user po kliknuti na plan ide na login.
- [ ] Prihlaseny user otvori promotion modal.
- [ ] Chatbot dropdown sa naplni listingami usera.
- [ ] Empty listing state ukaze no listing warning.
- [ ] Billing period v modali funguje.
- [ ] Upgrade note sa zobrazi len ked ma.
- [ ] Confirm button ukaze loader.
- [ ] Stripe redirect overlay sa zobrazi pocas presmerovania.
- [ ] View My Promotions link funguje po relevantnom stave.

## Checkout a Stripe

- [ ] Checkout sa nacita s platnymi query parametrami.
- [ ] Checkout s chybajucimi parametrami nepadne fatal errorom.
- [ ] Stripe script sa nacita.
- [ ] Checkout config sa cita z `#checkoutConfig`.
- [ ] Stripe loading message sa zobrazi a potom zmizne.
- [ ] Payment element sa zobrazi.
- [ ] Express checkout wrapper sa sprava spravne.
- [ ] Pay button loader funguje.
- [ ] Error box sa zobrazi pri chybe.
- [ ] Saved payment method state funguje, ak user ma ulozenu kartu.
- [ ] Different payment method flow funguje.
- [ ] Uspech presmeruje na success/profile podla flowu.
- [ ] Zly Stripe response ukaze user-friendly chybu.
- [ ] Apple Pay / Google Pay otestovat cez Stripe CLI/browser, ak je dostupne.

## Contact

- [ ] Contact page sa nacita.
- [ ] Flash success sa ukaze cez notification.
- [ ] Flash error sa ukaze cez notification.
- [ ] Subject dropdown sa otvori/zavrie.
- [ ] Subject dropdown podporuje keyboard Enter, Space, Arrow Up/Down, Escape.
- [ ] Submit prazdneho formulara ukaze validacne chyby.
- [ ] Submit validneho formulara odosle spravu.
- [ ] CSRF chyba nepadne fatal errorom.
- [ ] Honeypot/anti-spam spravanie ostava funkcne.

## Admin

- [ ] Admin access/login funguje.
- [ ] Admin page sa nacita bez CSP errorov.
- [ ] Admin tabs/messages/reviews/submissions sa prepnu.
- [ ] Admin refresh nacita nove data.
- [ ] Admin action buttons funguju bez inline event handlerov.
- [ ] Reply/contact action funguje.
- [ ] Review approve/reject/flag actions funguju.
- [ ] Submission approve/reject actions funguju.
- [ ] Admin settings notification email add/delete funguju.
- [ ] Block user funguje a vrati JSON.
- [ ] Admin popupy sa otvaraju a zatvaraju.
- [ ] Console nema JS chyby po kliku na admin controls.

## API endpointy

Skontrolovat cez UI alebo Postman/curl:

- [ ] `api/getChatbots.php`
- [ ] `api/getFavourites.php`
- [ ] `api/getFeatureCounts.php`
- [ ] `api/getIntegrationCounts.php`
- [ ] `api/getPricingCounts.php`
- [ ] `api/getCurrencyCounts.php`
- [ ] `api/toggleFavorite.php`
- [ ] `api/submitReview.php`
- [ ] `api/updateReview.php`
- [ ] `api/recordVisit.php`
- [ ] `api/identify.php`
- [ ] `api/revokeConsent.php`
- [ ] `api/stripeWebhook.php`

Ocakavanie:

- JSON odpoved ma `success`.
- Chyby maju rozumny HTTP status.
- Endpoint nevracia HTML fatal error.
- Pri nevalidnom vstupe sa nic nezapise nekorektne.

## Emails

- [ ] Sign up verification email sa odosle.
- [ ] Reset password email sa odosle.
- [ ] Contact admin notification email sa odosle.
- [ ] Submission notification email sa odosle.
- [ ] Review notification email sa odosle.
- [ ] Marketing email script v test mode neposle realnym userom, ak nema.
- [ ] Unsubscribe link funguje.
- [ ] Email linky su spravne escapovane.
- [ ] Email HTML sa zobrazi prijatelne v Gmaili.

## SEO a sitemap

- [ ] Homepage structured data je stale v HTML.
- [ ] Rich Results Test pre homepage nema kriticke chyby.
- [ ] `sitemap.php` sa nacita.
- [ ] Sitemap obsahuje spravne URL.
- [ ] Canonical/meta tagy na hlavnych strankach ostali.
- [ ] 404 state na review stranke je korektny.

## Responsive test

Otestovat aspon tieto sirky:

- [ ] 390 px mobile
- [ ] 768 px tablet
- [ ] 1024 px laptop
- [ ] 1440 px desktop

Na kazdej sirke:

- [ ] Header nepreteka.
- [ ] Sidebar/menu funguje.
- [ ] Buttons nemaju text mimo kontajner.
- [ ] Modaly su scrollovatelne a nezaseknu body scroll.
- [ ] Chatbot cards nepretekaju.
- [ ] Checkout a advertise modal su pouzitelne.

## Browser console a network

Na hlavnych strankach otvor DevTools:

- [ ] Console bez `ReferenceError`.
- [ ] Console bez CSP violation pre app JS.
- [ ] Network bez 404 na nove JS/CSS assety.
- [ ] API requesty vracaju JSON, nie HTML error.
- [ ] Stripe requesty sa nevykonavaju na strankach, kde nemaju.
- [ ] Analytics consent default sa vykona pred gtag configom.

## Minimalny regression run pred commitom

- [ ] PHP lint vsetkych PHP suborov.
- [ ] JS syntax check vsetkych `public/js/*.js`.
- [ ] `git diff --check`.
- [ ] Inline event handler scan.
- [ ] Manualne: header dropdown oko.
- [ ] Manualne: mobile sidebar Log Out.
- [ ] Manualne: chatbot showcase filters/search/rating.
- [ ] Manualne: favourites remove last item.
- [ ] Manualne: profile reviews edit/delete.
- [ ] Manualne: advertise modal.
- [ ] Manualne: checkout payment form.
- [ ] Manualne: contact submit.
- [ ] Manualne: admin core actions.

## Co commitnut po uspesnom teste

Pred commitom este raz:

```powershell
git status --short
git diff --stat
git diff --check
```

Potom commit spravit az ked manualny checklist nema blokery.

Navrh commit message:

```text
Harden sessions APIs and clean UI state handling
```

Ak chces rozdelit na viac commitov:

- `Harden sessions csrf and visitor tracking`
- `Normalize API responses and admin actions`
- `Clean inline scripts and UI hidden states`
- `Fix showcase favourites profile and checkout flows`
