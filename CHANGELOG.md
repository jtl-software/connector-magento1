1.1.0.0 (unreleased)
--------------------
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
