/*
** Copyright (C) 2010 Artem "Aly" Suharev
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
// JavaScript Document

var PMasters = new Array();				// obj instances 
function initPMaster(pmid, args){		// use this function to initialize PMaster
	if(typeof(PMasters[pmid]) == 'undefined'){
		PMasters[pmid] = new CPMaster(pmid,args);
	}
return pmid;
}

// Puppet master Class
// Author: Aly
var CPMaster = Class.create(CDebug,{
pmasterid:			0,				// PMasters reference id
dolls:				new Array(),	// list of updated objects

initialize: function($super, pmid, obj4upd){
	this.pmasterid = pmid;
	$super('CPMaster['+pmid+']');
//--
	
	var doll = new Array();
	for(id in obj4upd){
		if((typeof(obj4upd[id]) != 'undefined') && (!is_null(obj4upd[id]))){
			doll = obj4upd[id];
			
			if(typeof(doll['frequency']) == 'undefined')	doll['frequency'] = 60;
			if(typeof(doll['url']) == 'undefined') 			doll['url'] = location.href;
			if(typeof(doll['counter']) == 'undefined') 		doll['counter'] = 0;
			if(typeof(doll['darken']) == 'undefined') 		doll['darken'] = 0;
			if(typeof(doll['params']) == 'undefined') 		doll['params'] = new Array();
			
			this.addStartDoll(id, doll.frequency, doll.url, doll.counter, doll.darken, doll.params);
		}
	}
},

addStartDoll: function(domid,frequency,url,counter,darken,params){
	this.addDoll(domid,frequency,url,counter,darken,params);
	this.dolls[domid].startDoll();
	
return this.dolls[domid];
},

addDoll: function(domid,frequency,url,counter,darken,params){
	this.debug('addDoll', domid);
	
	var obj = document.getElementById(domid);
	if((typeof(obj) == 'undefined')) return false; 

	if(typeof(this.dolls[domid]) != 'undefined'){ 
		return this.dolls[domid];
	}
	
	var obj4update = {
		'domid': 		domid,
		'url': 			url,
		'params': 		params,
		'frequency': 	frequency,
		'darken':		darken,
		'lastupdate': 	0,
		'counter': 		0,
		'ready': 		true
	}

	this.dolls[domid] = new CDoll(obj4update);
	this.dolls[domid]._pmasterid = this.pmasterid;
	
return this.dolls[domid];
},

rmvDoll: function(domid){
	this.debug('rmvDoll', domid);
	
	if((typeof(this.dolls[domid]) != 'undefined') && (!is_null(this.dolls[domid]))){
		this.dolls[domid].pexec.stop();
		this.dolls[domid].pexec = null;
		
		this.dolls[domid].rmvDarken();
		
		try{ delete(this.dolls[domid]); } catch(e){ this.dolls[domid] = null; }
	}
},

startAllDolls: function(){
	this.debug('startAllDolls');
	
	for(domid in this.dolls){
		if((typeof(this.dolls[domid]) != 'undefined') && (!is_null(this.dolls[domid]))){
			this.dolls[domid].startDoll();
		}
	}
},

stopAllDolls: function(){
	this.debug('stopAllDolls');
	
	for(domid in this.dolls){
		if((typeof(this.dolls[domid]) != 'undefined') && (!is_null(this.dolls[domid]))){
			this.dolls[domid].stopDoll();
		}
	}
},

clear: function(){
	this.debug('clear');
	
	for(domid in this.dolls){
		this.rmvDoll(domid);
	}
	this.dolls = new Array();
}
});

// JavaScript Document
// DOM obj light loader (DOLL)
// Author: Aly
var CDoll = Class.create(CDebug,{
_pmasterid:		0,			// PMasters id to which doll belongs
_domobj:		null,		// DOM obj for update
_domid:			null,		// DOM obj id
_domdark:		null,		// DOM div fro darken updated obj
_url:			'',
_frequency:		60,			// min 5 sec
_darken:		0,			// make updated object darken - 1
_lastupdate:	0,
_counter:		0,			// how many times do update, 0 - infinite
_params:		'',
_status:		false,
_ready:			false,

pexec:			null,		// PeriodicalExecuter object
min_freq:		5,			// seconds

initialize: function($super, obj4update){
	this._domid = obj4update.domid;
	$super('CDoll['+this._domid+']');
//--

	this._domobj = $(this._domid);
	this.url(obj4update.url);
	this.frequency(obj4update.frequency);
	this.lastupdate(obj4update.lastupdate);
	this.darken(obj4update.darken);
	this.counter(obj4update.counter);
	this.params(obj4update.params);
	this.ready(obj4update.ready);
},

startDoll: function(){
	this.debug('startDoll');

	if(is_null(this.pexec)){
		this.lastupdate(0);
		this.pexec = new PeriodicalExecuter(this.check4Update.bind(this), this._frequency);
		this.check4Update();
	}
},

restartDoll: function(){
	this.debug('restartDoll');	
	if(!is_null(this.pexec)){
		this.pexec.stop();		
		try{ delete(this.pexec); } catch(e){ this.pexec = null; }
		this.pexec = null;
	}

	this.pexec = new PeriodicalExecuter(this.check4Update.bind(this), this._frequency);
},

stopDoll: function(){
	this.debug('stopDoll');
	
	if(!is_null(this.pexec)){
		this.pexec.stop();		
		try{ delete(this.pexec); } catch(e){ this.pexec = null; }
		this.pexec = null;
	}
},

pmasterid: function(){
	return this._pmasterid;
},

domid: function(){
	return this._domid;
},

domobj: function(){
	return this._domobj;
},

url: function(url_){
	if('undefined'==typeof(url_)) return this._url;
	else this._url=url_;
},

frequency: function(frequency_){
	if('undefined'==typeof(frequency_)){
		return this._frequency;
	}
	else{
		if(frequency_ < this.min_freq) frequency_ = this.min_freq;
		this._frequency=parseInt(frequency_);
	}
},

lastupdate: function(lastupdate_){
	if('undefined'==typeof(lastupdate_)) return this._lastupdate;
	else this._lastupdate=lastupdate_;
},

darken:	function(darken_){
	if('undefined'==typeof(darken_)) return this._darken;
	else this._darken=darken_;
},

counter: function(counter_){
	if('undefined'==typeof(counter_)) return Math.abs(this._counter);
	else this._counter=counter_;
},

ready: function(ready_){
	if('undefined'==typeof(ready_)) return this._ready;
	else this._ready=ready_;
},

params: function(params_){
	if('undefined'==typeof(params_)) return this._params;
	else this._params=params_;
},

check4Update: function(){
	this.debug('check4Update');
	
	var now = parseInt(new Date().getTime()/1000);

//SDI((this._lastupdate + this._frequency)+' < '+(now + this.min_freq));
	if(this._ready && ((this._lastupdate + this._frequency) < (now + this.min_freq))){ //
		this.update();
		this._lastupdate = now; 
	}
},

update: function(){
	this.debug('update');

	this._ready = false;
	
	if(this._counter == 1) this.pexec.stop();
	if(this._darken) this.setDarken();
	
	var url = new Curl(this._url);
	url.setArgument('upd_counter', this.counter());
	url.setArgument('pmasterid', this.pmasterid());

	new Ajax.Request(url.getUrl(),
					{
						'method': 'post',
						'parameters': this._params,
						'onSuccess': this.onSuccess.bind(this),
						'onFailure': this.onFailure.bind(this)
					}
	);

	this._counter--;
},

onSuccess: function(resp){
	this.debug('onSuccess');
	this.rmwDarken();

	var headers = resp.getAllResponseHeaders(); 
//alert(headers);
	if(headers.indexOf('Ajax-response: false') > -1){
		return false;
	}
	else{
		this._domobj.update(resp.responseText);
	}
//SDI(resp.responseText);

	this._ready = true;
	this.notify(true, this._pmasterid, this._domid, this._lastupdate, this.counter());
},

onFailure: function(resp){
	this.debug('onFailure');
	
	this.rmwDarken();
	this._ready = true;
	this.notify(false, this._pmasterid, this._domid, this._lastupdate, this.counter());
},

setDarken: function(){
	this.debug('setDarken');
	
	if(is_null(this._domobj)) return false;
	
	if(is_null(this._domdark)){
		this._domdark = document.createElement('div');		
		document.body.appendChild(this._domdark);
		this._domdark.className = 'onajaxload';
	}
	
	var obj_params = getPosition(this._domobj);
	obj_params.height = this._domobj.offsetHeight;
	obj_params.width = this._domobj.offsetWidth;
	
	Element.extend(this._domdark);
	this._domdark.setStyle({ 'top': obj_params.top+'px', 
							'left': obj_params.left+'px',
							'width': obj_params.width+'px',
							'height': obj_params.height+'px'
							});
},

rmwDarken: function(){
	this.debug('rmvDarken');
	
	if(!is_null(this._domdark)){
		this._domdark.style.cursor = 'auto';
		
		document.body.removeChild(this._domdark);
		this._domdark = null;
	}
}
});