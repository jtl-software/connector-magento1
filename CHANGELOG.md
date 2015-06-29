1.1.0.0
-------
- Use Magento's table name prefix for plain SQL

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
