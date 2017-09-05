# Magento Multi Carrier Shipping

Shipping module for Magento, using table rate calc and Correios webservice

# Instalation
Exec this commands from your store **root path**
```sh
$ git submodule add git@github.com:cammino/magento-multicarriershipping.git app/code/community/Cammino/Multicarriershipping #Add submodule to the project
$ cp app/code/community/Cammino/Multicarriershipping/Cammino_Multicarriershipping.xml app/etc/modules/ #Module declaration
$ cp app/code/community/Cammino/Multicarriershipping/Cammino_Multicarriershipping.csv app/locale/pt_BR/ #Translate file
$ cp app/code/community/Cammino/Multicarriershipping/tablerate.xml app/design/adminhtml/default/default/layout/tablerate.xml #Block layout declaration
```

# Initial Configs
Create the following attributes and link them with your products:  
* width, height, depth (text with decimal number validation);  
* multicarrier_carrier (combobox with options 'Correios', 'Tablerate');

Go to `System -> Configuration -> Carriers` and set the desired configs inside the three main tabs of the module: 
* Multi Carrier; 
* Multi Carrier - Tabela de Frete;
* Multi Carrier - Web Service Correios;
# Usage
You can easily find the Tablerate config inside the admin console of Magento, menu Sales, Table Rate (Tabela de Fretes)