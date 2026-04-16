# Guide Complet de Configuration Hotspot MikroTik & Backend (Actuel & V7)

Ce projet permet de mettre en place un portail captif MikroTik personnalisé, incluant la collecte de données des clients (Nom, Téléphone, etc.) via un serveur backend PHP externe.

---

## 1. Fonctionnement Global

1. **Le Portail** : Lors d'une tentative de connexion, l'utilisateur est redirigé vers `login.html`.
2. **La Collecte** : L'utilisateur remplit ses informations. Au clic sur "Se connecter", le JavaScript envoie ces données au serveur backend PHP via une requête.
3. **L'Authentification** : Une fois la réponse du serveur reçue, le portail valide la connexion classique auprès du routeur MikroTik à l'aide de l'API PAP interne.
4. **La Synchronisation** : Un script de maintenance sur le serveur se connecte régulièrement à l'API du MikroTik pour vérifier les sessions actives, en conservant la cohérence avec la base de données.

---

## 2. Configuration de la Base de Données (Backend)

L'ordinateur ou serveur hébergeant le code PHP doit disposer d'un serveur MySQL/MariaDB.
Vous devez créer une base de données (ex: `ctn`) et exécuter le schéma SQL ci-dessous pour créer les tables.

### Schéma de la Base de Données
```sql
CREATE TABLE `'ctn'wifi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `contact` varchar(255) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `mac_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_time` timestamp NULL DEFAULT NULL,
  `logout_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pseudo` varchar(50) NOT NULL,
  `motdepasse` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
*(Remarque : Assurez-vous d'insérer un compte admin pour démarrer, avec un mot de passe sécurisé à l'aide de `password_hash()` en PHP).*

### Scripts Backend
Dans le dossier `backend`, veillez à remplacer tous les placeholders `[...]` par vos informations réelles (adresses IP, logs BDD, identifiants MikroTik).

---

## 3. Configuration du Routeur MikroTik (Winbox & CLI)

Pour que ce système fonctionne, le serveur backend doit être à une IP fixe et être joignable par tout ordinateur (même non-authentifié). 
*(Exemple: le serveur est sur l'IP `192.168.88.254`).*

### A. Importer le portail sur le routeur

**Via l'Interface Graphique (Winbox/WebFig) :**
1. Ouvrez l'onglet **Files** dans Winbox.
2. Glissez-déposez l'intégralité du dossier `config-page-connexion` (contenant `login.html`, etc.) à la racine ou dans le dossier `flash/` de votre routeur.

**Sélectionner le dossier du portail :**
1. **Winbox** : Allez dans **IP** > **Hotspot** > Onglet **Server Profiles**. Double-cliquez sur votre profil actif (ex: `hsprof1`).
2. Allez dans l'onglet **General**. Mettez le champ `HTML Directory` sur le dossier `config-page-connexion` (ou `flash/config-page-connexion`).
3. Allez dans l'onglet **Login**, assurez-vous de cocher la méthode **HTTP PAP**. (Cela permet au formulaire personnalisé de soumettre directement les logs).

**Via CLI :**
```bash
/ip hotspot profile set [find default=yes] html-directory=config-page-connexion login-by=http-pap
```

---

### B. Assigner une Adresse IP Fixe au Serveur Backend

Si votre backend est physiquement relié au réseau DHCP du MikroTik, fixez son IP.

**Via l'Interface Graphique (Winbox) :**
1. Allez dans **IP** > **DHCP Server** > Onglet **Leases**.
2. Repérez la ligne correspondant à votre Serveur Backend grâce à son adresse MAC.
3. Double-cliquez dessus. Si le bouton **Make Static** est visible, cliquez dessus et validez. 
4. Double-cliquez à nouveau, et changez l'**Address** pour l'IP fixe que vous avez choisie (ex: `192.168.88.254`). Validez avec **OK**. L'ordinateur doit potentiellement se déconnecter/reconnecter pour prendre l'IP.

**Via CLI :**
```bash
# Pour assigner manuellement le bail
/ip dhcp-server lease add address=192.168.88.254 mac-address=XX:XX:XX:XX:XX:XX server=all comment="Serveur Backend"
```

---

### C. Éviter la redirection du portail pour le Serveur (IP Binding Bypass)

Pour garantir que votre ordinateur serveur backend puisse accéder à Internet et ne se voit pas bloqué par sa propre page captive, il faut "bypasser" son adresse MAC des règles du Hotspot.

**Via l'Interface Graphique (Winbox) :**
1. Allez dans **IP** > **Hotspot** > Onglet **IP Bindings**.
2. Cliquez sur le signe **+** (Ajouter).
3. Entrez la **MAC Address** de votre serveur.
4. Dans le champ **Type**, choisissez **bypassed**.
5. Cliquez sur **OK**.

**Via CLI :**
```bash
/ip hotspot ip-binding add mac-address=XX:XX:XX:XX:XX:XX type=bypassed comment="Bypass Complet Serveur Backend"
```

---

### D. Permettre aux Clients d'atteindre le Serveur (Walled Garden & Filtres)

Pour que la page `login.html` puisse envoyer les données via Javascript (`fetch`), l'ordinateur du client doit avoir la permission réseau de contacter l'IP du backend avant d'être connecté au web. Le `Walled Garden` sert de jardin clos autorisé.

**Via l'Interface Graphique (Winbox) :**
1. Allez dans **IP** > **Hotspot** > Onglet **Walled Garden IP List** (Walled Garden complet).
2. Cliquez sur le signe **+** (Ajouter).
3. Choisissez l'**Action** : `accept`.
4. Dans le champ **Dst. Address** (L'adresse de destination autorisée), tapez l'IP de votre serveur : `192.168.88.254`.
5. Cliquez sur **OK**.

**Via CLI :**
```bash
/ip hotspot walled-garden ip add dst-address=192.168.88.254 action=accept comment="Acces Serveur Backend pour Clients"
```

*(Optionnel) S'assurer des Firewall Filters :*
Si le serveur est sur le même sous-réseau `LAN`, il sera toujours accessible ; mais si votre portail captive bloque tout de façon stricte en Bridge ou autre réseau complexe, ajoutez la permission de routage vers cette adresse `Destination Port: 80 ou 443`.

---

## 4. Script de Synchronisation
Le script serveur `check_deconnexions.php` compare périodiquement ce que possède le MikroTik (`/ip hotspot active`) avec la base de données.
Vous pouvez configurer une Tâche Planifiée (Windows) ou un **CRON Job** (Linux/Unix) qui lance ce script toutes les X minutes pour maintenir des historiques fiables de `login_time` et `logout_time`.

---

## 5. Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

