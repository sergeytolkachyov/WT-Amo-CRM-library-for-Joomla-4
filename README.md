# WT Amo CRM library for Joomla 4
[More info on developer site](https://web-tolk.ru/dev/biblioteki/wt-amo-crm-library)

A small PHP library for Joomla 4 and Amo CRM. For developers.
As part of the package
- amoCRM connection library
- settings plugin for connecting to Amo CRM System - WT Amo CRM Library
### Connecting the library to your Joomla extension
```
use Webtolk\Amocrm\Amocrm;
$amocrm = new Amocrm();
$result_amo_crm = $amocrm->getAccountInfo();
```
### Amo CRM Fields for Joomla Form
The library contains a set of Joomla Form fields with information obtained from Amo CRM.
#### Accountinfo
Outputs html with data about the Amo CRM account. Example of using Joomla 4 modules and plugins in XML manifests.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="accountinfo" name="accountinfo"/>
```
#### Companiestagslist -Companies tags list
A list of tags for companies in Amo CRM. An example of using Joomla 4 modules and plugins in XML manifests.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="companiestagslist" name="company_tag_id"/>
```
#### Contactstagslist -Contacts tags list
A list of tags for contacts in Amo CRM. An example of using Joomla 4 modules and plugins in XML manifests.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="contactstagslist" name="contact_tag_id"/>
```
#### Leadcustomfieldslist -Lead custom fields list
A list of custom Amo CRM fields for transactions.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="leadcustomfieldslist" name="lead_custom_field_id"/>
```
#### Leadspipelineslist -Leads pipelines list
List of Amo CRM sales funnels.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="leadspipelineslist" name="pipeline_id"/>
```
#### Leadstagslist -Leads tags list
List of tags for deals. 
Params:
- limit - tags count in list. Max 250
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="leadstagslist" limit="100" name="lead_tag_id"/>
```
## How to install
- [Installing the library in Joomla 4](https://web-tolk.ru/en/dev/joomla-libraries/wt-amo-crm-library/documentation)
- [How to connect the AmoCRM library to your Joomla extension](https://web-tolk.ru/en/dev/joomla-libraries/wt-amo-crm-library/documentation/kak-podklyuchit-biblioteku-amocrm-v-svojo-rasshirenie-dlya-joomla)
## List of library methods
- [getAccountInfo](https://web-tolk.ru/en/dev/joomla-libraries/wt-amo-crm-library/documentation/method-getaccountinfo)
- [getLeadById](https://web-tolk.ru/en/dev/joomla-libraries/wt-amo-crm-library/documentation/metod-getleadbyid)
- [createLeads](https://web-tolk.ru/en/dev/joomla-libraries/wt-amo-crm-library/documentation/method-createleads)
- [createLeadsComplex](https://web-tolk.ru/en/dev/joomla-libraries/wt-amo-crm-library/documentation/method-createleadscomplex)
- [getTags](https://web-tolk.ru/en/dev/joomla-libraries/wt-amo-crm-library/documentation/method-gettags)
- getLeadsPiplines
- getLeadsCustomFields
- getContactsCustomFields
- getCompaniesCustomFields
- getCustomersCustomFields
- getContacts
- getUserById

Docs in progress. Methods are described in library code (PHP Doc block)
