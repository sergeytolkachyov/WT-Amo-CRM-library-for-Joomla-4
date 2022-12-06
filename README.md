# WT Amo CRM library for Joomla 4
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
#### Companiestagslist - Companies tags list 
A list of tags for companies in Amo CRM. An example of using Joomla 4 modules and plugins in XML manifests.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="companiestagslist" name="company_tag_id"/>
```
#### Contactstagslist - Contacts tags list
A list of tags for contacts in Amo CRM. An example of using Joomla 4 modules and plugins in XML manifests.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="contactstagslist" name="contact_tag_id"/>
```
#### Leadcustomfieldslist - Lead custom fields list
A list of custom Amo CRM fields for transactions.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="leadcustomfieldslist" name="lead_custom_field_id"/>
```
#### Leadspipelineslist - Leads pipelines list
List of Amo CRM sales funnels.
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="leadspipelineslist" name="pipeline_id"/>
```
#### Leadstagslist - Leads tags list
List of tags for deals
```
<field addfieldprefix="Webtolk\Amocrm\Fields" type="leadstagslist" name="lead_tag_id"/>
```
##List of library methods
- getAccountInfo
- getLeadById
- createLeads
- createLeadsComplex
- getTags
- getLeadsPiplines
- getLeadsCustomFields
- getContactsCustomFields
- getCompaniesCustomFields
- getCustomersCustomFields
- getContacts
- getUserById

Docs in progress. Methods are described in library code (PHP Doc block)
