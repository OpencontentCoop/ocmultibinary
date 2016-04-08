ALTER TABLE ezbinaryfile DROP CONSTRAINT ezbinaryfile_pkey;
ALTER TABLE ONLY ezbinaryfile ADD CONSTRAINT ezbinaryfile_pkey PRIMARY KEY (contentobject_attribute_id , version, filename );