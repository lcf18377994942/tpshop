<template>
    <div class="table">
        <div class="crumbs">
            <el-breadcrumb separator="/">
                <el-breadcrumb-item><i class="el-icon-lx-cascades"></i>商品管理</el-breadcrumb-item>
                <el-breadcrumb-item>规格列表</el-breadcrumb-item>
            </el-breadcrumb>
        </div>
        <div class="container">
            <div>
                <el-button icon="el-icon-add" @click="handleAdd"  type="success">添加规格</el-button>
            </div>
            <el-table :data="tableData" border class="table" ref="multipleTable">

                <el-table-column prop="id" label="规格ID" width="100"></el-table-column>
                <el-table-column prop="sort" label="排序" width="100"> </el-table-column>
                <el-table-column prop="spec_name" label="规格名称" > </el-table-column>

                <el-table-column label="操作" width="240" align="center">
                    <template slot-scope="scope">
                        <el-button type="text" icon="el-icon-edit" @click="addSpecVal(scope.$index, scope.row)">添加规格值</el-button>
                        <el-button type="text" icon="el-icon-edit" v-show="scope.row.id!=1" @click="handleEdit(scope.$index, scope.row)">编辑</el-button>
                        <el-button type="text" icon="el-icon-delete"  v-show="scope.row.id!=1" class="red" @click="handleDelete(scope.$index, scope.row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <!-- 编辑弹出框 -->
        <el-dialog :title="id>0?'编辑':'添加'" :visible.sync="editVisible" width="30%">
            <el-form ref="form" :model="form" label-width="100px">
                <el-form-item label="规格名称">
                    <el-input v-model="form.spec_name"></el-input>
                </el-form-item>
                          
                <el-form-item label="排序">
                    <el-input v-model="form.sort"></el-input>
                </el-form-item>                
            </el-form>
            <span slot="footer" class="dialog-footer">
                <el-button @click="editVisible = false">取 消</el-button>
                <el-button type="primary" @click="saveEdit">确 定</el-button>
            </span>
        </el-dialog>

        <!-- 删除提示框 -->
        <el-dialog title="提示" :visible.sync="delVisible" width="300px" center>
            <div class="del-dialog-cnt">删除不可恢复，是否确定删除？</div>
            <span slot="footer" class="dialog-footer">
                <el-button @click="delVisible = false">取 消</el-button>
                <el-button type="primary" @click="deleteRow">确 定</el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script>
    import upload from '@/components/utils/upload';
    export default {
        name: 'goods_class',
        components:{upload},
        data() {
            return {
                tableData: [],
                cur_page: 1,
                editVisible: false,
                delVisible: false,
                form: {
                    spec_name: '',
                    sort:'',
                },
                id:0,
            }
        },
        created() {
            this.getData();
        },        
        activated() {
            this.getData();
        },

        methods: {
            // 获取数据
            getData() {
                this.$post_('goods/goods-spec/list',{},(res)=>{
                    console.log(res);
                    this.tableData = res.data;
                });
            },

            //添加
            handleAdd(){
                this.form.spec_name = '';
                this.form.sort = 0;
                this.form.id = 0;
                this.id = 0;               
                this.editVisible = true;
            },

            //修改
            handleEdit(index, row) {
                this.id = row.id;
                const item = this.tableData[index];
                this.form = {
                    spec_name: item.spec_name,
                    sort: item.sort,
                    id:this.id,

                }
                this.editVisible = true;
            },
            handleDelete(index, row) {
                this.id = row.id;
                this.delVisible = true;
            },

            // 保存编辑
            saveEdit() {
                this.$post_('goods/goods-spec/edit',this.form,(res)=>{
                    console.log(res);
                    if(res.code=='0'){
                        this.getData();
                        this.$message.success(res.msg);
                    }else{
                        this.$message.warning(res.msg);
                    }
                })
                this.editVisible = false;
            },
            // 确定删除
            deleteRow(){
                this.$post_('goods/goods-spec/del',{id:this.id},(res)=>{
                    console.log(res);
                    if(res.code=='0'){
                        this.$message.success(res.msg);
                        this.getData();
                    }else{
                        this.$message.warning(res.msg);
                    }
                })
                this.delVisible = false;
            },
            //添加规格值
            addSpecVal(index,row){
                this.$router.push({path:'/page/goods/goods_spec_value',query:{spec_id:row.id}})
            },

        }
    }

</script>

<style scoped>
    .iconfont{
        font-size: 20px;
        /*font-weight: bold;*/
    }
    .handle-box {
        margin-bottom: 20px;
    }

    .handle-select {
        width: 120px;
    }

    .handle-input {
        width: 300px;
        display: inline-block;
    }
    .del-dialog-cnt{
        font-size: 16px;
        text-align: center
    }
    .table{
        width: 100%;
        font-size: 14px;
    }
    .red{
        color: #ff0000;
    }
</style>
