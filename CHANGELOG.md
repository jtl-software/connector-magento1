1.4.6.0 (unreleased)
--------------------

1.4.5.0
-------
- Fix image pull queries
- Recognize already shipped orders
- Fix missing $websiteId while updating products
- Ensure that all creations of delivery notes return valid ID pairs

1.4.4.0
-------
- Limit all operations to simple and configurable products
- Support discount codes

1.4.3.0
-------
- Update jtl/connector to version 2.2.5
- Import specifics as select or textarea depending on the type selected in JTL-Wawi
- Fix payment status for postpaid methods

1.4.2.0
-------
- Improve the performance of image.statistic and image.pull
- Completely rewritten image import process
- Fix SQL query in payment.pull
- Mark all products as "In Stock" that permit backorders
- Fix tier price logic for guests

1.4.1.0
-------
- Improve image update and delete operations
- Fix 'Can't use method return value in write context at specific.push'
- Fix logic bug in specific update code
- Allow 'false' and 'true' to be used for predefined category attributes
- Add more payment method mappings
- Import paid orders with correct payment state
- Update jtl/connector to version 2.2.2
- Fix handling of predefined category attributes with regard to boolean values

1.4.0.3
-------
- Add support for is_anchor on categories

1.4.0.2
-------
- Exclude cancelled and holded orders from being pulled into JTL-Wawi
- Fix plugin path in composer.json

1.4.0.1
-------
- Update jtlconnector to version 2.2.1 to improve HHVM compatibility

1.4.0.0
-------
- Fix product variation import
- Update jtlconnector to version 2.2.0
- Introduce predefined category function attributes
- Put jtlconnector dependencies into Magento's system-wide lib directory
- Fix modman deployment for Magento 1.9.2.2

1.3.4.0
-------
- Allow memory_limit to be specified in bytes (useful for 64-Bit builds or HHVM)
- Fix reference parameter passing to str_replace
- Update jtlconnector to version 2.1.0

1.3.3.0
-------
- Fix multiselect specific support

1.3.2.0
-------
- Remove unneeded and deprecated code
- Remove all traces of the old configuration system
- Always return full model data upon delete operations

1.3.1.0
-------
- Fix a bug causing EAV product attribute enumeration to fail
- Provide fallback values to limit customer.pull and customer_order.pull
- Fix MSRP being stored without tax

1.3.0.1
-------
- Update to the latest version of jtlconnector including a new protocol version

1.3.0.0
-------
- Experimental support for specifics
- Bypass flat catalogs for all operations
- Improve image ID mapping
- Fix generated synchronization URL when "Add store code to URL" is active
- Fix calculation of order totals and quantities when using IPN-based payment methods

1.2.0.0
-------
- Fix handling of customer's company and VAT number
- Fix localized attribute names not being set correctly
- Fix missing simple product name on checkout pages
- Add VAT ID information to billing addresses

1.1.1.1
-------
- Added floating point values support for php ini configurations
- Changed identify serverinfo byte values to megabyte

1.1.1.0
-------
- Add multiple image push support

1.1.0.1
-------
- Remove old order status change code

1.1.0.0
-------
- Fix handling of product applicable to backorders
- Fix handling of divisible products
- Fix product metadata import
- Add support for additional payment methods and fix payment module mapping
- Fix a bug that caused core.linker.clear to fail
- Provide a list of shipping methods to be mapped in JTL-Wawi
- Add support for delivery notes
- Skip flat index generation if they have been disabled before (fixes #1)
- Fix "Duplicate key" errors when updating varcombi parents

1.0.6.0
-------
- Reduce log verbosity by defining most log messages as Logger::DEBUG
- Fix handling of image.delete requests incoming as array
- Pull payments grouped to chunks of size 25
- Respect category isActive flag and provide a fallback evaluation of the respective category function attribute

1.0.5.0
-------
- Use Magento's table name prefix for plain SQL
- Use jtlconnector's facilities to convert between locales and three-digit ISO language codes
- Check for empty product collections while calculating image statistics
- Fix old setters for variation value names in customer orders
- Reduce page size for product.pull to prevent memory_limit exceptions
- Be error-tolerant while deleting products and categories

1.0.4.0
-------
- Remove Twig as it is not used anymore
- Fix wrong version number in etc/config.xml
- Fix pagination issues with products
- Speedup image handling drastically by employing raw SQL
- Add SQLite3 as a dependency to the package XML
- Bump minimum PHP version to 5.4
- Introduce protocol version number evaluated by JTL-Wawi

1.0.3.0
-------
- Bump internal version identification
- Set package stability to "Beta"
- Fix class overrides to suppress PHP warnings

1.0.2.0
-------
- Remove old tax rate priority
- Include child entities in features.json to inform JTL-Wawi if they are supported or not
- Implement new interface for Garbage Collection
- Fix missing parent information for configurable children (http://developer.jtl-software.de/issues/11584)
- Experimental support for handling payment transactions

1.0.1.0
-------
- Show synchronization URL in backend

1.0.0.0
-------
- First public release
