# Passerelle de paiement Axepta pour WooCommerce

Accepter les paiements par carte bancaire sur votre boutique WooCommerce en utilisant la passerelle de paiement Axepta.

## Configuration requise

* PHP : 7.3 et plus
* WordPress : 5.6.9 et plus
* WooCommerce : 5.8 et plus
* ModRewrite sur votre serveur Apache
* Un compte Axepta - BNP Paribas avec un environnement 3DS2 acceptant les appels à Platform HTML Forms (paySSL)

Il se peut que le plugin fonctionne sur des versions antérieures de PHP, WordPress ou WooCommerce, mais il n'a pas été testé dans d'autres conditions que celles mentionnées ci-dessus.

## Installation & mises à jour

La première installation est manuelle. [Téléchargez l'archive ici](https://depot.studiomaiis.net/wordpress/woocommerce-gateway-axepta.zip) et installez le plugin de manière standard :
* via FTP une fois l'archive décompressée dans `wp-content/plugins/`,
* ou via la page d'administration des extensions.

Si vous téléchargez le code depuis Github, renommer le répertoire `wp-plugin-woocommerce-gateway-axepta(*)` en `woocommerce-gateway-axepta`.

Une fois le module installé, les mises à jour se font depuis le back-office de WordPress.

## Questions fréquentes

### A qui s'adresse ce plugin ?

Aux développeurs et aux intégrateurs.

### Ce plugin supporte-t'il les remboursements ?

Non. Il existe d'autres plugins (payants) qui proposent les remboursements. Aucune affiliation avec les auteurs de ces plugins.

### Ce plugin supporte-t'il les paiements récurrents, les abonnements ?

Non. Il existe d'autres plugins (payants) qui proposent des paiements récurrents. Aucune affiliation avec les auteurs de ces plugins.

### Est-ce qu'un certificat SSL est nécessaire ?

Non, ce n'est pas nécessaire, mais c'est hautement recommandé pour ajouter un niveau supplémentaire de sécurité pour vos clients. Techniquement, à la validation d'une commande, les utilisateurs sont redirigés vers les serveurs *Axepta* via une requête HTTPS.

### Ce plugin supporte-til les environnements de test et de production ?

Oui, les réglages de ces 2 modes sont stockés en base et le passage de l'un à l'autre se fait par le biais d'une case à cocher dans l'administration du mode de paiement. Les identifiants de test sont ceux de l'environnement générique *Axepta*. Ces derniers sont en lecture seule car les identifiants de votre environnement de test dédié (dont le MID se termine par `_t`) ne fonctionnent actuellement pas en 3DS2 ([cf. documentation](https://docs.axepta.bnpparibas/display/DOCBNP/Test+modes))

### Comment puis-je configurer ce module de paiement ?

Si vous avez le MerchantID (ou MID dans la doc), la clé HMAC et la clé de chiffrement Blowfish, vous avez tout ce qu'il vous faut et vous n'aurez aucun mal à les saisir aux endroits réservés !

Veuillez vous référer à la [documentation officielle](https://docs.axepta.bnpparibas/display/DOCBNP/Premiers+pas+avec+AXEPTA+BNP+Paribas) pour récupérer vos identifiants.

Attention à ne pas utiliser les tickets Github pour des demandes de support.

### Sur l'environnement de test, où puis-je trouver les cartes de test ?

Les cartes de test peuvent être trouvées [ici](https://docs.axepta.bnpparibas/display/DOCBNP/Test+Cards+-+Authentication).

### Un support, une aide sont-ils proposés pour récupérer les informations MerchantId, clés HMAC et Blowfish ?

Non, ce plugin s'adresse plutôt à des développeurs et intégrateur qui savent récupérer ces informations. Veuillez vous référer à la [documentation officielle](https://docs.axepta.bnpparibas/display/DOCBNP/Premiers+pas+avec+AXEPTA+BNP+Paribas) ou contacter le support *Axepta*.

Cherchez de l'aide dans la [section discussion de ce projet Github](https://github.com/studiomaiis/wp-plugin-woocommerce-gateway-axepta/discussions).

Si vraiment vous ne savez pas faire, vous pouvez m'envoyer une demande sur mon [site internet](https://www.studiomaiis.net), toute intervention fera l'objet d'un devis et d'une facture. Sachez que récupérer ces informations peut s'avérer assez chronophage.

### Comment passe-t'on en mode production ?

Lorsque vous avez récupéré vos identifiants de production MerchantID, les clés HMAC et Blowfish, saisissez-les dans la configuration du prestataire et activez-le.

### J'ai trouvé un bug, comment puis-je le faire remonter ?

Séquence à suivre :
1. Vérifiez que vous avez la configuration requise : PHP, WordPress et WooCommerce,
2. Vérifiez que votre serveur est accessible - exemple : si vous développez en local, les serveurs Axepta ne pourront pas envoyer leurs réponses à votre serveur,
3. Activez les logs et visualisez leurs contenus,
4. Testez avec le thème Storefront et désactivez les plugins qui pourraient interférer,
5. Regardez dans la [section discussion de ce projet](https://github.com/studiomaiis/wp-plugin-woocommerce-gateway-axepta/discussions) pour voir si d'autres utilisateurs n'ont pas eu et/ou résolu le même problème,
6. Contactez le support *Axepta*, ils peuvent avoir des informations additionnelles,
7. Soumettez un bug sur [GitHub](https://github.com/studiomaiis/wp-plugin-woocommerce-gateway-axepta/issues).

S'il s'agit d'une demande de support, vous pouvez m'envoyer une demande sur mon [site internet](https://www.studiomaiis.net), toute intervention fera l'objet d'un devis et d'une facture. 

