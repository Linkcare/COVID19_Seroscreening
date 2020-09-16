# COVID19_Seroscreening
WS-API Service for COVID-19 Seroscreening Project

Contents of the project:

Web site
========
Used to show the information related to as Seroscreening kit (Kit ID, Manufacture date, etc.) and invoke LC2 to start a workflow in the "COVID-19 Seroscreening" PROGRAM.
Uses a independent database to store the information of the Kits.

REST services
=============
To be invoked from LC2 when a Seroscreening kit is scanned and communicate with the appropriate Linkcare Instance to generate the necessary TASKs in an ADMISSION

SOAP services
=============
To be invoked from FORMULAS in the "COVID-19 Seroscreening" PROGRAM.
The goal of the published SOAP service is to update the status of the Kit in the independent database
