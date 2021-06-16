<template>

    <!-- Main content -->
    <div class="content-wrapper">

        <!-- Page header -->
        <div class="page-header page-header-light d-print-none">
            <div class="page-header-content header-elements-md-inline">
                <div class="page-title d-flex">
                    <h4><i class="icon-files-empty2 mr-2"></i> <span class="font-weight-semibold">Journals</span></h4>
                    <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
                </div>

            </div>

            <div class="breadcrumb-line breadcrumb-line-light header-elements-md-inline">
                <div class="d-flex">
                    <div class="breadcrumb">
                        <a href="/" class="breadcrumb-item">
                            <i class="icon-home2 mr-2"></i>
                            <span class="badge badge-primary badge-pill font-weight-bold rg-breadcrumb-item-tenant-name"> {{this.$root.tenant.name | truncate(30) }} </span>
                        </a>
                        <span class="breadcrumb-item">Accounting</span>
                        <span class="breadcrumb-item">Advanced</span>
                        <span class="breadcrumb-item active">Journals</span>
                    </div>

                    <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
                </div>

                <div class="header-elements">
                    <div class="breadcrumb justify-content-center">
                        <router-link to="/journal-entries/create"
                                     class="btn btn-outline btn-primary border-primary text-primary-800 border-2 btn-sm rounded font-weight-bold mr-2">
                            <i class="icon-files-empty2 mr-1"></i>
                            New Journal
                        </router-link>

                    </div>
                </div>
            </div>
        </div>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content border-0 p-0">

            <loading-txn-animation></loading-txn-animation>

            <!-- Content area -->
            <div class="content" v-if="!this.$root.loadingTxn && txnData">

                <!-- txn template -->
                <div v-if="txnData.status === 'draft'"
                     class="card border-left-3 border-warning rounded-0 max-width-960 ml-auto mr-auto rg-print-border-0">
                    <div class="card-header header-elements-inline d-print-none text-danger">
                        <h6 class="card-title font-weight-bold" v-if="txnData.type">
                            Approve {{txnData.type.name}}<br>
                            <small>You are viewing a draft</small>
                        </h6>
                        <div class="header-elements">
                            <button type="button"
                                    class="btn bg-success font-weight-bold mr-1"
                                    @click="txnApprove('/journal-entries/'+txnData.id+'/approve')"><i class="icon-file-check2 mr-2"></i> Click here to Approve</button>

                            <router-link :to="'/journal-entries/'+$route.params.id+'/edit'"
                                         class="btn bg-danger font-weight-bold">
                                <i class="icon-pencil7 mr-2"></i>
                                Edit
                            </router-link>

                        </div>
                    </div>
                </div>

                <div class="card max-width-960 m-auto rg-print-border-0">

                    <div class="card-header bg-transparent header-elements-inline d-print-none">
                        <h6 class="card-title" v-if="txnData.type">{{txnData.type.name}} #{{txnData.number}}</h6>
                        <div class="header-elements">

                            <router-link :to="'/journal-entries/'+$route.params.id+'/copy'"
                                         class="btn btn-light btn-sm">
                                <i class="icon-copy mr-2"></i>
                                Copy
                            </router-link>

                            <button type="button" class="btn btn-light btn-sm ml-1" onclick="window.print();"><i class="icon-printer mr-2"></i> Print</button>
                            <a :href="'/journal-entries/'+$route.params.id+'/pdf'" type="button" class="btn btn-light btn-sm ml-1"><i class="icon-file-pdf mr-2"></i>Pdf</a>

                        </div>
                    </div>

                    <div class="card-body">

                        <div class="row"
                             v-if="$root.tenant.logo">
                            <div class="col-sm-6 mb-2">
                                <img :src="'/timthumb.php?src=storage/' + $root.tenant.logo + '&h=27&q=100'" class="" :alt="$root.tenant.name" >
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="mb-4">
                                    <ul class="list list-unstyled mb-0">
                                        <li>
                                            <h5 class="rg-font-weight-600">{{$root.tenant.name}}</h5>
                                        </li>
                                        <li v-if="$root.tenant.street_line_1">{{$root.tenant.street_line_1}}</li>
                                        <li v-if="$root.tenant.street_line_2">{{$root.tenant.street_line_2}}</li>
                                        <li v-if="$root.tenant.city">City: {{$root.tenant.city}}</li>
                                        <li v-if="$root.tenant.state_province">State: {{$root.tenant.state_province}}</li>
                                        <li v-if="$root.tenant.phone">Phone: {{$root.tenant.phone}}</li>
                                        <li v-if="$root.tenant.website">Website: {{$root.tenant.website}}</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="mb-4">
                                    <div class="text-sm-right">
                                        <h4 class="text-primary mb-2 mt-md-2" >Journal Entry</h4>
                                        <ul class="list list-unstyled mb-0">
                                            <li>Date: <span class="font-weight-semibold">{{txnData.date}}</span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-md-flex flex-md-wrap">
                            <div class="mb-4 mb-md-2">
                                <span class="text-muted" v-if="txnData.type">{{txnData.type.name}} To:</span>
                                <ul v-if="txnData.contact" class="list list-unstyled mb-0">
                                    <li><h5 class="my-2">{{txnData.contact.contact_salutation}} {{txnData.contact_name}}</h5></li>
                                    <li v-if="txnData.contact.shipping_address_street1 && txnData.contact.shipping_address_street2">
                                        <span class="font-weight-semibold">{{txnData.contact.shipping_address_street1}} {{txnData.contact.shipping_address_street2}}</span>
                                    </li>
                                    <li v-if="txnData.contact.shipping_address_city">{{txnData.contact.shipping_address_city}}</li>
                                    <li v-if="txnData.contact.shipping_address_state">{{txnData.contact.shipping_address_state}}</li>
                                    <li v-if="txnData.contact.shipping_address_country">{{txnData.contact.shipping_address_country}}</li>
                                    <li v-if="txnData.contact.contact_work_phone">{{txnData.contact.contact_work_phone}}</li>
                                    <li v-if="txnData.contact.contact_email"><a href="#">{{txnData.contact.contact_email}}</a></li>
                                </ul>
                            </div>


                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-lg">
                            <thead>
                            <tr class="bg-light">
                                <th class="font-weight-bold">Account</th>
                                <th class="font-weight-bold">Description</th>
                                <th class="font-weight-bold">Contact</th>
                                <th class="font-weight-bold text-right">Debit <small> {{txnData.base_currency}}</small></th>
                                <th class="font-weight-bold text-right">Credit <small> {{txnData.base_currency}}</small></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="recording in txnData.recordings"
                                v-if="!['txn', 'txn_type', 'tax'].includes(recording.type)">
                                <td>
                                    <h6 class="mb-0">{{recording.financial_account.name}}</h6>
                                </td>
                                <td class="">{{recording.description}}</td>
                                <td class="">{{recording.contact_name}}</td>
                                <td class="text-right">
                                    <span class="font-weight-semibold">{{rgNumberFormat(recording.debit, 2)}}</span>
                                </td>
                                <td class="text-right">
                                    <span class="font-weight-semibold">{{rgNumberFormat(recording.credit, 2)}}</span>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-body pr-0">
                        <div class="d-md-flex flex-md-wrap">
                            <div class="pt-2 mb-3 text-muted">
                                <h6>Authorized Stamp / Signature</h6>
                            </div>

                        </div>
                    </div>
                    <div class="card-body">
                        <h6>Amount in words:</h6>
                        <p class="text-muted">{{txnData.total_in_words}}</p>
                    </div>

                </div>
                <!-- /invoice template -->

            </div>
            <!-- /content area -->

        </div>
        <!-- /content area -->


        <!-- Footer -->

        <!-- /footer -->

    </div>
    <!-- /main content -->

</template>

<script>

    export default {
        name: 'AccountingSalesEstimatesShow',
        //components: {},
        data() {
            return {}
        },
        watch: {
            $route (to, from) {
                if (this.txnShowId !== this.$route.params.id) this.txnFetchData()
            }
        },
        mounted() {
            this.$root.appMenu('accounting')

            this.txnFetchData() //get the details of the transaction

            this.txnShowId = this.$route.params.id

        },
        methods: {},
        ready:function(){},
        beforeUpdate: function () {},
        updated: function () {
            this.txnShowId = this.$route.params.id
            if(this.txnData.type) {
                document.title = this.txnData.type.name + ' ' + this.txnData.number
            }
        }
    }
</script>
