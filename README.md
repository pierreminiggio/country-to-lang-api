# country-to-lang-api

Migration :
```sql

--
-- Structure de la table `country_lang`
--

CREATE TABLE `country_lang` (
  `id` int(11) NOT NULL,
  `country` char(2) NOT NULL,
  `lang` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `country_lang`
--
ALTER TABLE `country_lang`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `country_lang`
--
ALTER TABLE `country_lang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Structure de la table `unprocessable_request`
--

CREATE TABLE `unprocessable_request` (
  `id` int(11) NOT NULL,
  `request` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `unprocessable_request`
--
ALTER TABLE `unprocessable_request`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `unprocessable_request`
--
ALTER TABLE `unprocessable_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;COMMIT;

```
