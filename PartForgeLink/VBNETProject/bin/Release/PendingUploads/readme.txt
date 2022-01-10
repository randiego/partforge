Add files with the extension .pforgex to this directory for upload.  The files should be in the following format.
See https://github.com/randiego/partforge/wiki/PartForgeLink-exe-auto-uploading-test-data for an explanation:




<measurement>
   <header>
    <title>FlightTest_2020_10_02</title>
    <effective_date>10/02/2020 07:32</effective_date>
    <typeobject_id>9</typeobject_id>
    <disposition>Pass</disposition>
  </header>
  <data>
    <field name="drone">XTD001</field>
    <field name="hover_test">1</field>
    <field name="low_battery_test">1</field>
    <field name="post_hover_battery_temperature">56</field>
  </data>
  <comment>
    <comment_date></comment_date>
    <text><![CDATA[Battery Temperature Log.]]></text>
    <fileattachment>C:\MyDataLocation\FlightTest\FlightLog_2020-10-02-0732.png</fileattachment>
    <fileattachment>C:\MyDataLocation\FlightTest\FlightLog_2020-10-02-0732.csv</fileattachment>
  </comment>
</measurement>


An example with an attachment type field:

<measurement>
	<header>
	<typeobject_id>1194</typeobject_id>
	<item_serial_number>SN000070</item_serial_number>
	</header>
	<data>
	  <report_attachment>
		<comment>
		<text><![CDATA[Battery Temperature Log.]]></text>
		<fileattachment>C:\wamp64\www\partforge\PartForgeLink\VBNETProject\bin\Release\PendingUploads\TPA-0340A-00 CofC SN000070.pdf</fileattachment>
		<fileattachment>C:\wamp64\www\partforge\PartForgeLink\VBNETProject\bin\Release\PendingUploads\TPA-0340A-00 CofC SN000070.pdf</fileattachment>
		</comment>
	  </report_attachment>
	</data>
</measurement>
