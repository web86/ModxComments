Ext.namespace('ModxComments','ModxComments.grid','ModxComments.panel');

ModxComments.config={
    connectorUrl: MODx.config.assets_url+'components/modxcomments/mgr-connector.php'
};

ModxComments.renderStatus=function(value){
    var labels={
        published:_('modxcomments.published'),
        pending:_('modxcomments.pending'),
        spam:_('modxcomments.spam'),
        deleted:_('modxcomments.delete')
    };
    var label=labels[value]||value;
    return '<span class="mc-mgr-status mc-mgr-status-'+Ext.util.Format.htmlEncode(value)+'">'
        +Ext.util.Format.htmlEncode(label)+'</span>';
};

ModxComments.renderEmail=function(value){
    if(!value) return '<span class="mc-mgr-muted">—</span>';
    return '<a class="mc-mgr-email" href="mailto:'+Ext.util.Format.htmlEncode(value)+'">'
        +Ext.util.Format.htmlEncode(value)+'</a>';
};

ModxComments.grid.Comments=function(config){
    config=config||{};

    Ext.applyIf(config,{
        id:'modxcomments-grid-comments',
        url:ModxComments.config.connectorUrl,
        baseParams:{action:'mgr/comment/getlist'},
        fields:[
            'id','resource_id','resource_title','parent_id','thread_id','depth','path',
            'author_name','author_email','content','parent_author','parent_excerpt',
            'is_admin','status','createdon'
        ],
        paging:true,
        remoteSort:true,
        pageSize:20,
        autoHeight:true,
        columns:[
            {header:'ID',dataIndex:'id',width:55,fixed:true},
            {header:_('modxcomments.resource'),dataIndex:'resource_title',width:180},
            {
                header:_('modxcomments.author'),
                dataIndex:'author_name',
                width:150,
                renderer:function(value,meta,record){
                    var name=Ext.util.Format.htmlEncode(value||'');
                    if(record.data.is_admin){
                        return '<span class="mc-mgr-admin-badge" title="'+_('modxcomments.admin_reply')+'">★ '+_('modxcomments.admin')+'</span> '
                            +'<strong>'+name+'</strong>';
                    }
                    return name;
                }
            },
            {header:_('modxcomments.email'),dataIndex:'author_email',width:190,renderer:ModxComments.renderEmail},
            {
                header:_('modxcomments.comment'),
                dataIndex:'content',
                width:430,
                renderer:function(value,meta,record){
                    var depth=Math.max(0,parseInt(record.data.depth,10)||0);
                    var indent=Math.min(depth,6)*18;
                    var html='<div class="mc-mgr-thread-cell" style="padding-left:'+indent+'px">';

                    if(depth>0){
                        html+='<div class="mc-mgr-reply-meta">↳ '+_('modxcomments.reply_to');
                        if(record.data.parent_author){
                            html+=' <strong>'+Ext.util.Format.htmlEncode(record.data.parent_author)+'</strong>';
                        }
                        if(record.data.parent_excerpt){
                            html+=' <span>“'+Ext.util.Format.htmlEncode(record.data.parent_excerpt)+'”</span>';
                        }
                        html+='</div>';
                    }else{
                        html+='<div class="mc-mgr-root-meta">'+_('modxcomments.thread_root')+'</div>';
                    }

                    html+='<div class="mc-mgr-comment-text">'+Ext.util.Format.htmlEncode(value||'')+'</div>';
                    html+='</div>';
                    return html;
                }
            },
            {header:_('modxcomments.status'),dataIndex:'status',width:110,renderer:ModxComments.renderStatus},
            {header:_('modxcomments.createdon'),dataIndex:'createdon',width:135}
        ],
        tbar:[
            {
                xtype:'textfield',
                width:240,
                emptyText:_('modxcomments.search'),
                viewConfig:{
            getRowClass:function(record){
                var cls=[];
                if(record.data.is_admin){
                    cls.push('mc-mgr-row-admin');
                }
                if((parseInt(record.data.depth,10)||0)>0){
                    cls.push('mc-mgr-row-reply');
                }else{
                    cls.push('mc-mgr-row-root');
                }
                return cls.join(' ');
            }
        },
        listeners:{change:{fn:this.search,scope:this,buffer:400}}
            },
            {
                text:_('modxcomments.clear'),
                cls:'mc-mgr-clear',
                handler:this.clearSearch,
                scope:this
            },
            '-',
            {text:_('modxcomments.all'),handler:function(){this.filterStatus('');},scope:this},
            {text:_('modxcomments.published'),handler:function(){this.filterStatus('published');},scope:this},
            {text:_('modxcomments.pending'),handler:function(){this.filterStatus('pending');},scope:this},
            {text:_('modxcomments.spam'),handler:function(){this.filterStatus('spam');},scope:this}
        ],
        listeners:{
            rowcontextmenu:function(grid,rowIndex,event){
                event.stopEvent();
                grid.getSelectionModel().selectRow(rowIndex);
                grid.showMenu(grid.getStore().getAt(rowIndex),event);
            },
            rowdblclick:function(grid,rowIndex){
                var record=grid.getStore().getAt(rowIndex);
                grid.showMenu(record,null);
            }
        }
    });

    ModxComments.grid.Comments.superclass.constructor.call(this,config);
};

Ext.extend(ModxComments.grid.Comments,MODx.grid.Grid,{
    search:function(field){
        this.searchField=field;
        this.getStore().baseParams.query=field.getValue();
        this.getBottomToolbar().changePage(1);
    },

    clearSearch:function(){
        if(this.searchField){
            this.searchField.setValue('');
        }
        this.getStore().baseParams.query='';
        this.getBottomToolbar().changePage(1);
    },

    filterStatus:function(status){
        this.getStore().baseParams.status=status;
        this.getBottomToolbar().changePage(1);
    },

    showMenu:function(record,event){
        var menu=new Ext.menu.Menu({
            items:[
                {
                    text:_('modxcomments.publish'),
                    iconCls:'icon icon-check',
                    handler:function(){this.setStatus(record.id,'published');},
                    scope:this
                },
                {
                    text:_('modxcomments.mark_pending'),
                    iconCls:'icon icon-clock-o',
                    handler:function(){this.setStatus(record.id,'pending');},
                    scope:this
                },
                {
                    text:_('modxcomments.mark_spam'),
                    iconCls:'icon icon-ban',
                    handler:function(){this.setStatus(record.id,'spam');},
                    scope:this
                },
                '-',
                {
                    text:_('modxcomments.delete'),
                    iconCls:'icon icon-trash-o',
                    handler:function(){this.removeComment(record.id);},
                    scope:this
                }
            ]
        });

        if(event){
            menu.showAt(event.getXY());
        }else{
            var view=this.getView();
            var row=view.getRow(this.getStore().indexOf(record));
            var xy=Ext.fly(row).getXY();
            menu.showAt([xy[0]+40,xy[1]+20]);
        }
    },

    setStatus:function(id,status){
        MODx.Ajax.request({
            url:ModxComments.config.connectorUrl,
            params:{action:'mgr/comment/status',id:id,status:status},
            listeners:{
                success:{
                    fn:function(){
                        this.refresh();
                        MODx.msg.status({title:_('success'),message:_('modxcomments.status_updated')});
                    },
                    scope:this
                }
            }
        });
    },

    removeComment:function(id){
        MODx.msg.confirm({
            title:_('modxcomments.delete'),
            text:_('modxcomments.delete_confirm'),
            url:ModxComments.config.connectorUrl,
            params:{action:'mgr/comment/remove',id:id},
            listeners:{
                success:{
                    fn:function(){
                        this.refresh();
                        MODx.msg.status({title:_('success'),message:_('modxcomments.deleted')});
                    },
                    scope:this
                }
            }
        });
    }
});

Ext.reg('modxcomments-grid-comments',ModxComments.grid.Comments);

ModxComments.panel.Home=function(config){
    config=config||{};

    Ext.apply(config,{
        border:false,
        baseCls:'modx-formpanel',
        cls:'container',
        items:[
            {
                html:'<div class="mc-mgr-heading"><h2>'+_('modxcomments')+'</h2>'
                    +'<p>'+_('modxcomments.intro')+'</p></div>',
                border:false,
                cls:'modx-page-header'
            },
            {xtype:'modxcomments-grid-comments',cls:'main-wrapper'}
        ]
    });

    ModxComments.panel.Home.superclass.constructor.call(this,config);
};

Ext.extend(ModxComments.panel.Home,MODx.Panel);
Ext.reg('modxcomments-panel-home',ModxComments.panel.Home);
