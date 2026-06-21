<?php
/**
 * Droit de rétractation (UE/EEE) + formulaire-type de rétractation
 * (Muster-Widerrufsformular). Pour les visiteurs hors UE : renvoi à la
 * politique de retour du vendeur. Modèle à faire valider juridiquement.
 */
$L  = legal_ctx($forced_cc ?? null);
$op = $L['operator'];

// Repli sur l'anglais pour toute locale UI sans traduction dédiée ci-dessous.
$TX = [
    'en' => [
        'right_title'     => 'Right of withdrawal',
        'right_p1'        => 'As a consumer, you have the right to withdraw from this contract within 14 days without giving any reason. The withdrawal period expires 14 days from the day on which you (or a third party you indicate, other than the carrier) acquires physical possession of the goods.',
        'right_p2'        => 'To exercise it, inform the seller (and, if you wish, Afriklink) of your decision by an unambiguous statement (e.g. a letter sent by post or email). You may use the model form below, but it is not mandatory.',
        'effects_title'   => 'Effects of withdrawal',
        'effects_p'       => 'The seller reimburses all payments received, including standard delivery costs, without undue delay and within 14 days of being informed. You must send back the goods without undue delay and within 14 days; the direct cost of return may be borne by you. You are only liable for any diminished value resulting from handling beyond what is necessary.',
        'exceptions_title'=> 'Exceptions',
        'exceptions_p'    => 'The right of withdrawal does not apply, in particular, to: made-to-order or clearly personalised goods; goods liable to deteriorate or expire rapidly (incl. prepared meals); sealed goods unsealed after delivery for hygiene/health reasons; and services fully performed with your prior agreement.',
        'form_title'      => 'Model withdrawal form',
        'form_note'       => '(Complete and return this form only if you wish to withdraw from the contract.)',
        'form_to'         => 'To: the seller concerned, via Afriklink',
        'form_decl'       => 'I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*) / for the provision of the following service (*):',
        'form_ordered'    => 'Ordered on (*) / received on (*):',
        'form_ordernum'   => 'Order number:',
        'form_name'       => 'Name of consumer(s):',
        'form_addr'       => 'Address of consumer(s):',
        'form_date'       => 'Date:',
        'form_sign'       => 'Signature (only if this form is notified on paper):',
        'form_delete'     => 'Delete as appropriate.',
        'returns_title'   => 'Returns & refunds',
        'returns_p'       => 'The 14-day EU right of withdrawal does not apply in your country. Returns and refunds follow each seller\'s published policy and the consumer law applicable where you live. Contact the seller first; Afriklink helps resolve disputes in good faith.',
        'returns_contact' => 'Platform contact:',
        'back'            => 'Back to terms',
    ],
    'fr' => [
        'right_title'     => 'Droit de rétractation',
        'right_p1'        => "En tant que consommateur, vous avez le droit de vous rétracter du présent contrat sans donner de motif dans un délai de 14 jours. Le délai expire 14 jours après le jour où vous-même (ou un tiers que vous désignez, autre que le transporteur) prenez physiquement possession du bien.",
        'right_p2'        => "Pour l'exercer, informez le vendeur (et, si vous le souhaitez, Afriklink) de votre décision par une déclaration dénuée d'ambiguïté (courrier postal ou e-mail). Vous pouvez utiliser le formulaire-type ci-dessous, sans obligation.",
        'effects_title'   => 'Effets de la rétractation',
        'effects_p'       => "Le vendeur rembourse tous les paiements reçus, y compris les frais de livraison standard, sans retard injustifié et dans les 14 jours suivant l'information. Vous devez renvoyer le bien sans retard injustifié et dans les 14 jours ; le coût direct du renvoi peut rester à votre charge. Vous n'êtes responsable que de la dépréciation résultant de manipulations au-delà du nécessaire.",
        'exceptions_title'=> 'Exceptions',
        'exceptions_p'    => "Le droit de rétractation ne s'applique pas, notamment, aux : biens sur mesure ou nettement personnalisés ; biens susceptibles de se détériorer ou de périmer rapidement (y compris plats préparés) ; biens scellés descellés après livraison pour des raisons d'hygiène/santé ; et services pleinement exécutés avec votre accord préalable.",
        'form_title'      => 'Formulaire-type de rétractation',
        'form_note'       => '(Veuillez compléter et renvoyer ce formulaire uniquement si vous souhaitez vous rétracter du contrat.)',
        'form_to'         => "À l'attention : du vendeur concerné, via Afriklink",
        'form_decl'       => "Je/Nous (*) vous notifie/notifions (*) par la présente ma/notre (*) rétractation du contrat portant sur la vente du bien (*) / pour la prestation de service (*) ci-dessous :",
        'form_ordered'    => 'Commandé le (*) / reçu le (*) :',
        'form_ordernum'   => 'Numéro de commande :',
        'form_name'       => 'Nom du/des consommateur(s) :',
        'form_addr'       => 'Adresse du/des consommateur(s) :',
        'form_date'       => 'Date :',
        'form_sign'       => 'Signature (uniquement en cas de notification sur papier) :',
        'form_delete'     => 'Rayez la mention inutile.',
        'returns_title'   => 'Retours & remboursements',
        'returns_p'       => "Le droit de rétractation de 14 jours de l'UE ne s'applique pas dans votre pays. Les retours et remboursements suivent la politique publiée par chaque vendeur et le droit de la consommation applicable chez vous. Contactez d'abord le vendeur ; Afriklink facilite la résolution de bonne foi.",
        'returns_contact' => 'Contact plateforme :',
        'back'            => 'Retour aux conditions générales',
    ],
    'de' => [
        'right_title'     => 'Widerrufsrecht',
        'right_p1'        => 'Als Verbraucher haben Sie das Recht, diesen Vertrag binnen 14 Tagen ohne Angabe von Gründen zu widerrufen. Die Widerrufsfrist endet 14 Tage ab dem Tag, an dem Sie (oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist) die Waren in Besitz genommen haben.',
        'right_p2'        => 'Um es auszuüben, müssen Sie den Verkäufer (und, wenn Sie möchten, Afriklink) mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder eine E-Mail) über Ihren Entschluss informieren. Sie können das nachstehende Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.',
        'effects_title'   => 'Folgen des Widerrufs',
        'effects_p'       => 'Der Verkäufer erstattet alle erhaltenen Zahlungen einschließlich der Kosten der Standardlieferung unverzüglich und spätestens binnen 14 Tagen ab dem Tag der Unterrichtung zurück. Sie müssen die Waren unverzüglich und spätestens binnen 14 Tagen zurücksenden; die unmittelbaren Kosten der Rücksendung können Sie zu tragen haben. Sie haften nur für einen Wertverlust der Waren, der auf einen Umgang zurückzuführen ist, der über das Notwendige hinausgeht.',
        'exceptions_title'=> 'Ausnahmen',
        'exceptions_p'    => 'Das Widerrufsrecht besteht insbesondere nicht bei: nach Kundenwunsch angefertigten oder eindeutig personalisierten Waren; Waren, die schnell verderben können oder deren Verfallsdatum schnell überschritten würde (einschließlich zubereiteter Speisen); versiegelten Waren, die nach der Lieferung aus Gründen der Hygiene/des Gesundheitsschutzes entsiegelt wurden; sowie vollständig erbrachten Dienstleistungen mit Ihrer vorherigen Zustimmung.',
        'form_title'      => 'Muster-Widerrufsformular',
        'form_note'       => '(Füllen Sie dieses Formular aus und senden Sie es nur dann zurück, wenn Sie den Vertrag widerrufen möchten.)',
        'form_to'         => 'An: den betreffenden Verkäufer, über Afriklink',
        'form_decl'       => 'Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*) / die Erbringung der folgenden Dienstleistung (*):',
        'form_ordered'    => 'Bestellt am (*) / erhalten am (*):',
        'form_ordernum'   => 'Bestellnummer:',
        'form_name'       => 'Name des/der Verbraucher(s):',
        'form_addr'       => 'Anschrift des/der Verbraucher(s):',
        'form_date'       => 'Datum:',
        'form_sign'       => 'Unterschrift (nur bei Mitteilung auf Papier):',
        'form_delete'     => 'Unzutreffendes streichen.',
        'returns_title'   => 'Rückgaben & Erstattungen',
        'returns_p'       => 'Das 14-tägige Widerrufsrecht der EU gilt in Ihrem Land nicht. Rückgaben und Erstattungen richten sich nach den veröffentlichten Bedingungen des jeweiligen Verkäufers und dem an Ihrem Wohnort geltenden Verbraucherrecht. Wenden Sie sich zuerst an den Verkäufer; Afriklink unterstützt eine gütliche Beilegung von Streitigkeiten.',
        'returns_contact' => 'Kontakt der Plattform:',
        'back'            => 'Zurück zu den Allgemeinen Geschäftsbedingungen',
    ],
    'es' => [
        'right_title'     => 'Derecho de desistimiento',
        'right_p1'        => 'Como consumidor, tiene derecho a desistir del presente contrato en un plazo de 14 días sin necesidad de justificación. El plazo de desistimiento expirará a los 14 días del día en que usted (o un tercero indicado por usted, distinto del transportista) adquiera la posesión material de los bienes.',
        'right_p2'        => 'Para ejercerlo, deberá notificar al vendedor (y, si lo desea, a Afriklink) su decisión mediante una declaración inequívoca (por ejemplo, una carta enviada por correo postal o por correo electrónico). Puede utilizar el modelo de formulario que figura a continuación, aunque su uso no es obligatorio.',
        'effects_title'   => 'Efectos del desistimiento',
        'effects_p'       => 'El vendedor le reembolsará todos los pagos recibidos, incluidos los gastos de entrega estándar, sin demora indebida y, en todo caso, en un plazo de 14 días desde que sea informado. Deberá devolver los bienes sin demora indebida y, en todo caso, en un plazo de 14 días; el coste directo de la devolución podrá correr a su cargo. Solo será responsable de la disminución de valor de los bienes resultante de una manipulación distinta de la necesaria.',
        'exceptions_title'=> 'Excepciones',
        'exceptions_p'    => 'El derecho de desistimiento no se aplica, en particular, a: bienes confeccionados conforme a las especificaciones del consumidor o claramente personalizados; bienes que puedan deteriorarse o caducar con rapidez (incluidas las comidas preparadas); bienes precintados que hayan sido desprecintados tras la entrega por razones de higiene o de salud; y servicios completamente ejecutados con su consentimiento previo.',
        'form_title'      => 'Modelo de formulario de desistimiento',
        'form_note'       => '(Cumplimente y envíe el presente formulario únicamente si desea desistir del contrato.)',
        'form_to'         => 'A la atención: del vendedor afectado, a través de Afriklink',
        'form_decl'       => 'Por la presente le comunico/comunicamos (*) que desisto/desistimos (*) de mi/nuestro (*) contrato de venta del siguiente bien (*) / de prestación del siguiente servicio (*):',
        'form_ordered'    => 'Pedido el (*) / recibido el (*):',
        'form_ordernum'   => 'Número de pedido:',
        'form_name'       => 'Nombre del/de los consumidor(es):',
        'form_addr'       => 'Domicilio del/de los consumidor(es):',
        'form_date'       => 'Fecha:',
        'form_sign'       => 'Firma (solo si el presente formulario se notifica en papel):',
        'form_delete'     => 'Táchese lo que no proceda.',
        'returns_title'   => 'Devoluciones y reembolsos',
        'returns_p'       => 'El derecho de desistimiento de 14 días de la UE no se aplica en su país. Las devoluciones y los reembolsos se rigen por la política publicada por cada vendedor y por la legislación de consumo aplicable en su lugar de residencia. Póngase en contacto primero con el vendedor; Afriklink facilita la resolución de conflictos de buena fe.',
        'returns_contact' => 'Contacto de la plataforma:',
        'back'            => 'Volver a las condiciones generales',
    ],
    'it' => [
        'right_title'     => 'Diritto di recesso',
        'right_p1'        => 'In qualità di consumatore, ha il diritto di recedere dal presente contratto entro 14 giorni senza dover fornire alcuna motivazione. Il periodo di recesso termina dopo 14 giorni dal giorno in cui lei (o un terzo da lei designato, diverso dal vettore) acquisisce il possesso fisico dei beni.',
        'right_p2'        => 'Per esercitarlo, è tenuto a informare il venditore (e, se lo desidera, Afriklink) della Sua decisione mediante una dichiarazione esplicita (ad esempio una lettera inviata per posta o per e-mail). Può utilizzare il modulo tipo riportato di seguito, senza che ciò sia obbligatorio.',
        'effects_title'   => 'Effetti del recesso',
        'effects_p'       => 'Il venditore rimborsa tutti i pagamenti ricevuti, compresi i costi di consegna standard, senza indebito ritardo ed entro 14 giorni dal giorno in cui è informato. Lei deve restituire i beni senza indebito ritardo ed entro 14 giorni; il costo diretto della restituzione può restare a Suo carico. È responsabile unicamente della diminuzione di valore dei beni risultante da una manipolazione diversa da quella necessaria.',
        'exceptions_title'=> 'Eccezioni',
        'exceptions_p'    => 'Il diritto di recesso non si applica, in particolare, a: beni confezionati su misura o chiaramente personalizzati; beni che rischiano di deteriorarsi o scadere rapidamente (compresi i pasti preparati); beni sigillati che sono stati aperti dopo la consegna per motivi igienici o di salute; e servizi completamente eseguiti con il Suo previo consenso.',
        'form_title'      => 'Modulo tipo di recesso',
        'form_note'       => '(Compili e restituisca il presente modulo soltanto se desidera recedere dal contratto.)',
        'form_to'         => "All'attenzione: del venditore interessato, tramite Afriklink",
        'form_decl'       => 'Con la presente io/noi (*) notifico/notifichiamo (*) il recesso dal mio/nostro (*) contratto di vendita del seguente bene (*) / di prestazione del seguente servizio (*):',
        'form_ordered'    => 'Ordinato il (*) / ricevuto il (*):',
        'form_ordernum'   => 'Numero d\'ordine:',
        'form_name'       => 'Nome del/dei consumatore/i:',
        'form_addr'       => 'Indirizzo del/dei consumatore/i:',
        'form_date'       => 'Data:',
        'form_sign'       => 'Firma (solo se il presente modulo è notificato su carta):',
        'form_delete'     => 'Cancellare la voce inutile.',
        'returns_title'   => 'Resi e rimborsi',
        'returns_p'       => 'Il diritto di recesso di 14 giorni dell\'UE non si applica nel Suo Paese. I resi e i rimborsi seguono la politica pubblicata da ciascun venditore e il diritto dei consumatori applicabile nel Suo luogo di residenza. Contatti dapprima il venditore; Afriklink agevola la risoluzione delle controversie in buona fede.',
        'returns_contact' => 'Contatto della piattaforma:',
        'back'            => 'Torna alle condizioni generali',
    ],
    'pt' => [
        'right_title'     => 'Direito de retratação',
        'right_p1'        => 'Enquanto consumidor, tem o direito de se retratar do presente contrato no prazo de 14 dias sem indicar qualquer motivo. O prazo de retratação expira 14 dias a contar do dia em que adquira (ou um terceiro por si indicado, que não o transportador, adquira) a posse física dos bens.',
        'right_p2'        => 'Para o exercer, deve informar o vendedor (e, se assim o desejar, a Afriklink) da sua decisão através de uma declaração inequívoca (por exemplo, uma carta enviada pelo correio ou por e-mail). Pode utilizar o modelo de formulário abaixo, embora a sua utilização não seja obrigatória.',
        'effects_title'   => 'Efeitos da retratação',
        'effects_p'       => 'O vendedor reembolsa todos os pagamentos recebidos, incluindo os custos de entrega normal, sem demora injustificada e no prazo de 14 dias a contar da data em que for informado. Deve devolver os bens sem demora injustificada e no prazo de 14 dias; o custo direto da devolução pode ficar a seu cargo. Só é responsável pela depreciação dos bens decorrente de uma manipulação que exceda o necessário.',
        'exceptions_title'=> 'Exceções',
        'exceptions_p'    => 'O direito de retratação não se aplica, nomeadamente, a: bens feitos por medida ou claramente personalizados; bens suscetíveis de se deteriorarem ou de se tornarem rapidamente impróprios (incluindo refeições preparadas); bens selados que tenham sido abertos após a entrega por razões de higiene ou de saúde; e serviços integralmente prestados com o seu consentimento prévio.',
        'form_title'      => 'Modelo de formulário de retratação',
        'form_note'       => '(Preencha e devolva o presente formulário apenas se desejar retratar-se do contrato.)',
        'form_to'         => 'À atenção: do vendedor em causa, através da Afriklink',
        'form_decl'       => 'Pela presente comunico/comunicamos (*) que me retrato/nos retratamos (*) do meu/nosso (*) contrato de compra e venda do seguinte bem (*) / de prestação do seguinte serviço (*):',
        'form_ordered'    => 'Encomendado em (*) / recebido em (*):',
        'form_ordernum'   => 'Número da encomenda:',
        'form_name'       => 'Nome do(s) consumidor(es):',
        'form_addr'       => 'Endereço do(s) consumidor(es):',
        'form_date'       => 'Data:',
        'form_sign'       => 'Assinatura (apenas em caso de notificação em papel):',
        'form_delete'     => 'Riscar o que não interessa.',
        'returns_title'   => 'Devoluções e reembolsos',
        'returns_p'       => 'O direito de retratação de 14 dias da UE não se aplica no seu país. As devoluções e os reembolsos seguem a política publicada por cada vendedor e o direito do consumo aplicável no seu local de residência. Contacte primeiro o vendedor; a Afriklink facilita a resolução de litígios de boa-fé.',
        'returns_contact' => 'Contacto da plataforma:',
        'back'            => 'Voltar às condições gerais',
    ],
    'nl' => [
        'right_title'     => 'Herroepingsrecht',
        'right_p1'        => 'Als consument heeft u het recht om dit contract binnen 14 dagen zonder opgave van redenen te herroepen. De herroepingstermijn verstrijkt 14 dagen na de dag waarop u (of een door u aangewezen derde, die niet de vervoerder is) de goederen fysiek in bezit krijgt.',
        'right_p2'        => 'Om dit recht uit te oefenen, moet u de verkoper (en, indien u dat wenst, Afriklink) door middel van een ondubbelzinnige verklaring (bijvoorbeeld een per post of per e-mail verzonden brief) op de hoogte stellen van uw beslissing. U kunt hiervoor het onderstaande modelformulier gebruiken, maar dit is niet verplicht.',
        'effects_title'   => 'Gevolgen van de herroeping',
        'effects_p'       => 'De verkoper betaalt alle ontvangen betalingen terug, inclusief de kosten van de standaardlevering, onverwijld en in elk geval binnen 14 dagen nadat hij op de hoogte is gesteld. U moet de goederen onverwijld en in elk geval binnen 14 dagen terugzenden; de directe kosten van het terugzenden kunnen voor uw rekening komen. U bent alleen aansprakelijk voor de waardevermindering die het gevolg is van een behandeling die verder gaat dan noodzakelijk.',
        'exceptions_title'=> 'Uitzonderingen',
        'exceptions_p'    => 'Het herroepingsrecht is met name niet van toepassing op: op maat gemaakte of duidelijk gepersonaliseerde goederen; goederen die snel bederven of een beperkte houdbaarheid hebben (waaronder bereide maaltijden); verzegelde goederen die na levering om redenen van hygiëne of gezondheid zijn ontzegeld; en diensten die met uw voorafgaande instemming volledig zijn uitgevoerd.',
        'form_title'      => 'Modelformulier voor herroeping',
        'form_note'       => '(Vul dit formulier alleen in en zend het terug wanneer u het contract wilt herroepen.)',
        'form_to'         => 'Aan: de betrokken verkoper, via Afriklink',
        'form_decl'       => 'Hierbij deel ik/delen wij (*) u mede dat ik/wij (*) ons (*) koopcontract betreffende de volgende goederen (*) / de levering van de volgende dienst (*) herroep(en):',
        'form_ordered'    => 'Besteld op (*) / ontvangen op (*):',
        'form_ordernum'   => 'Bestelnummer:',
        'form_name'       => 'Naam van consument(en):',
        'form_addr'       => 'Adres van consument(en):',
        'form_date'       => 'Datum:',
        'form_sign'       => 'Handtekening (alleen wanneer dit formulier op papier wordt ingediend):',
        'form_delete'     => 'Doorhalen wat niet van toepassing is.',
        'returns_title'   => 'Retouren en terugbetalingen',
        'returns_p'       => 'Het EU-herroepingsrecht van 14 dagen geldt niet in uw land. Retouren en terugbetalingen volgen het gepubliceerde beleid van elke verkoper en het consumentenrecht dat geldt op uw woonplaats. Neem eerst contact op met de verkoper; Afriklink helpt geschillen te goeder trouw op te lossen.',
        'returns_contact' => 'Contact platform:',
        'back'            => 'Terug naar de algemene voorwaarden',
    ],
    'ar' => [
        'right_title'     => 'حق التراجع',
        'right_p1'        => 'بصفتك مستهلكًا، يحق لك التراجع عن هذا العقد خلال 14 يومًا دون إبداء أي سبب. تنتهي مهلة التراجع بعد مرور 14 يومًا من اليوم الذي تستلم فيه أنت (أو طرف ثالث تعيّنه، غير شركة النقل) الحيازة المادية للبضائع.',
        'right_p2'        => 'لممارسة هذا الحق، يتعيّن عليك إبلاغ البائع (و«أفريكلينك» إن رغبت) بقرارك من خلال تصريح لا لبس فيه (مثل رسالة تُرسل بالبريد العادي أو بالبريد الإلكتروني). يمكنك استخدام النموذج المرفق أدناه، غير أنّ ذلك ليس إلزاميًا.',
        'effects_title'   => 'آثار التراجع',
        'effects_p'       => 'يردّ البائع جميع المبالغ المستلمة، بما في ذلك تكاليف التسليم القياسية، دون تأخير لا مبرّر له وخلال 14 يومًا من إبلاغه بالقرار. يجب عليك إعادة البضائع دون تأخير لا مبرّر له وخلال 14 يومًا؛ وقد تتحمّل التكلفة المباشرة للإعادة. ولا تتحمّل المسؤولية إلا عن نقص قيمة البضائع الناجم عن تداولها بما يتجاوز ما هو ضروري.',
        'exceptions_title'=> 'الاستثناءات',
        'exceptions_p'    => 'لا ينطبق حق التراجع، على وجه الخصوص، على: البضائع المصنوعة حسب الطلب أو المخصّصة بوضوح؛ والبضائع القابلة للتلف أو انتهاء الصلاحية بسرعة (بما في ذلك الوجبات المُعدّة)؛ والبضائع المختومة التي فُضّ ختمها بعد التسليم لأسباب تتعلق بالنظافة أو الصحة؛ والخدمات المُنجَزة بالكامل بموافقتك المسبقة.',
        'form_title'      => 'نموذج التراجع',
        'form_note'       => '(يُرجى تعبئة هذا النموذج وإعادته فقط إذا كنت ترغب في التراجع عن العقد.)',
        'form_to'         => 'إلى عناية: البائع المعني، عبر «أفريكلينك»',
        'form_decl'       => 'أُخطركم/نُخطركم (*) بموجب هذا بأنني/بأننا (*) أتراجع/نتراجع (*) عن عقد بيع البضائع التالية (*) / عن تقديم الخدمة التالية (*):',
        'form_ordered'    => 'تاريخ الطلب (*) / تاريخ الاستلام (*):',
        'form_ordernum'   => 'رقم الطلب:',
        'form_name'       => 'اسم المستهلك/المستهلكين:',
        'form_addr'       => 'عنوان المستهلك/المستهلكين:',
        'form_date'       => 'التاريخ:',
        'form_sign'       => 'التوقيع (في حال الإخطار الورقي فقط):',
        'form_delete'     => 'اشطب ما لا يلزم.',
        'returns_title'   => 'الإرجاع والاسترداد',
        'returns_p'       => 'لا ينطبق حق التراجع الأوروبي البالغ 14 يومًا في بلدك. ويخضع الإرجاع والاسترداد لسياسة كل بائع المنشورة ولقانون حماية المستهلك الساري في مكان إقامتك. تواصل أولًا مع البائع؛ وتسهّل «أفريكلينك» تسوية النزاعات بحسن نية.',
        'returns_contact' => 'جهة اتصال المنصّة:',
        'back'            => 'العودة إلى الشروط العامة',
    ],
];
$T = $TX[current_locale()] ?? $TX['en'];
?>
<section class="legal-page">
    <h1><?= e(t('legal.withdrawal.title')) ?><?php if ($L['is_eu']): ?> <span class="legal-aka">/ Widerrufsrecht</span><?php endif; ?></h1>
    <p class="muted legal-updated"><?= e(t('legal.updated', ['date' => '20/06/2026'])) ?></p>

    <?= render_partial('partials/legal_regimes', ['current' => $L['regime'], 'base' => '/retractation']) ?>
    <div class="notice notice-warning"><p>⚠️ <?= e(t('legal.disclaimer')) ?></p></div>

    <?php if ($L['is_eu']): ?>
        <h2>1. <?= e($T['right_title']) ?></h2>
        <p><?= e($T['right_p1']) ?></p>
        <p><?= e($T['right_p2']) ?></p>

        <h2>2. <?= e($T['effects_title']) ?></h2>
        <p><?= e($T['effects_p']) ?></p>

        <h2>3. <?= e($T['exceptions_title']) ?></h2>
        <p><?= e($T['exceptions_p']) ?></p>

        <h2>4. <?= e($T['form_title']) ?></h2>
        <p class="muted"><?= e($T['form_note']) ?></p>
        <div class="legal-form-model">
            <p><?= e($T['form_to']) ?> — <?= e($op['email'] ?? 'contact@afriklink.com') ?></p>
            <p><?= e($T['form_decl']) ?></p>
            <p>……………………………………………………………………………………</p>
            <ul class="legal-form-fields">
                <li><?= e($T['form_ordered']) ?> …………………</li>
                <li><?= e($T['form_ordernum']) ?> …………………</li>
                <li><?= e($T['form_name']) ?> …………………</li>
                <li><?= e($T['form_addr']) ?> …………………</li>
                <li><?= e($T['form_date']) ?> …………………</li>
                <li><?= e($T['form_sign']) ?> …………………</li>
            </ul>
            <p class="muted">(*) <?= e($T['form_delete']) ?></p>
        </div>
    <?php else: ?>
        <h2><?= e($T['returns_title']) ?></h2>
        <p><?= e($T['returns_p']) ?></p>
        <p><?= e($T['returns_contact']) ?> <strong><?= e($op['email'] ?? 'contact@afriklink.com') ?></strong>.</p>
    <?php endif; ?>

    <p class="legal-back"><a href="<?= e(url('/cgv')) ?>">← <?= e($T['back']) ?></a></p>
</section>
