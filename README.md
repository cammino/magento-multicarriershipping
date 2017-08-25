# Magento Multi Carrier Shipping

Shipping module for Magento, using table rate calc and Correios webservice

# Instalation
Exec this commands from your store **root path**
```sh
$ git submodule add git@github.com:cammino/magento-multicarriershipping.git app/code/community/Cammino/Multicarriershipping #Add submodule to the project
$ cp app/code/community/Cammino/Multicarriershipping/Cammino_Multicarriershipping.xml app/etc/modules/ #Module declaration
$ cp app/code/community/Cammino/Multicarriershipping/Cammino_Multicarriershipping.csv app/locale/pt_BR/ #Translate file
$ cp app/code/community/Cammino/Multicarriershipping/tablerate.xml app/design/adminhtml/default/default/layout/tablerate.xml #Block layout declaration

# Usage
You can easily find the Tablerate config inside the admin console of Magento, menu Sales, Table Rate (Tabela de Fretes)
```